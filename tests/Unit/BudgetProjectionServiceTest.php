<?php

namespace Tests\Unit;

use App\Models\BudgetLoan;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Squad\Services\SquadService;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Regression tests for issue #1220. The previous season's carry-overs must
 * not follow a Pro Manager across a team switch — the BudgetProjectionService
 * receives an explicit $freshClub signal (published by
 * ApplyPendingTeamSwitchProcessor, forwarded by BudgetProjectionProcessor)
 * and skips carry-overs / prior-actual commercial revenue when set.
 */
class BudgetProjectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PREV_SEASON = 2025;
    private const CUR_SEASON = '2026';
    private const ACTUAL_SURPLUS = 10_000_000_00;
    private const CARRIED_SURPLUS = 2_000_000_00;
    private const ACTUAL_COMMERCIAL_REVENUE = 50_000_000_00;
    private const ACTUAL_TOTAL_REVENUE = 200_000_000_00;
    private const LOAN_REPAYMENT = 5_000_000_00;

    public function test_fresh_club_skips_carry_overs_and_uses_stadium_commercial_baseline(): void
    {
        [$service, $game, $team] = $this->buildScenario();

        $finances = $service->generateProjections($game, freshClub: true);

        $this->assertSame(0, $finances->carried_surplus);
        $this->assertSame(0, $finances->carried_debt);
        $this->assertSame(0, $finances->previous_loan_repayment);

        // First-season commercial baseline: stadium-seats × per-seat config
        // (default reputation = 'local' → 24_000 cents/seat, league tier 1
        // multiplier = 1.0). The exact figure depends on faker's seat count,
        // so we assert against the formula and confirm it isn't the prior
        // actual.
        $expectedCommercial = $team->stadium_seats * 24_000;
        $this->assertSame($expectedCommercial, $finances->projected_commercial_revenue);
        $this->assertNotSame(self::ACTUAL_COMMERCIAL_REVENUE, $finances->projected_commercial_revenue);
    }

    public function test_default_call_preserves_carry_overs_from_previous_season(): void
    {
        [$service, $game] = $this->buildScenario();

        $finances = $service->generateProjections($game);

        $expectedSurplus = self::ACTUAL_SURPLUS + self::CARRIED_SURPLUS;
        $this->assertSame($expectedSurplus, $finances->carried_surplus);
        $this->assertSame(0, $finances->carried_debt);
        $this->assertSame(self::LOAN_REPAYMENT, $finances->previous_loan_repayment);
        $this->assertSame(self::ACTUAL_COMMERCIAL_REVENUE, $finances->projected_commercial_revenue);
    }

    public function test_brand_floor_lifts_elite_commercial_above_the_stadium_baseline(): void
    {
        [$service, $game, $team] = $this->buildScenario();

        // Mark the club elite. With a 30K-seat ground the stadium-driven
        // commercial (30_000 × €1,700 = €51M) sits far below the elite brand
        // floor (€200M), so a global brand isn't throttled by a small stadium.
        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => ClubProfile::REPUTATION_ELITE,
        ]);

        $finances = $service->generateProjections($game, freshClub: true);

        $stadiumDriven = $team->stadium_seats * 170_000; // elite per-seat (cents)
        $brandFloor = config('finances.commercial_brand_floor.elite');

        $this->assertGreaterThan($stadiumDriven, $brandFloor);
        $this->assertSame($brandFloor, $finances->projected_commercial_revenue);
    }

    public function test_brand_floor_overrides_a_sub_brand_carried_commercial(): void
    {
        [$service, $game, $team] = $this->buildScenario();

        // Existing save: the prior-season actual commercial (€50M, set in
        // buildScenario) was settled before the brand floor existed. An elite
        // club must still be floored to its brand baseline at the next
        // projection rather than carrying the stale sub-brand figure.
        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => ClubProfile::REPUTATION_ELITE,
        ]);

        $finances = $service->generateProjections($game);

        $this->assertGreaterThan(self::ACTUAL_COMMERCIAL_REVENUE, config('finances.commercial_brand_floor.elite'));
        $this->assertSame(
            config('finances.commercial_brand_floor.elite'),
            $finances->projected_commercial_revenue,
        );
    }

    public function test_trailing_trading_allowance_averages_recent_net_sales(): void
    {
        // Disable the recurring-revenue guard so the raw average is observable.
        config(['finances.trading_allowance.max_fraction_of_recurring' => 1000.0]);

        [$service, $game] = $this->buildScenario();

        // Three completed seasons of net player-trading (sales − purchases).
        // buildScenario created the 2025 row; set its net result and add two
        // earlier seasons. Average = (€60M + €40M + €50M) / 3 = €50M.
        GameFinances::where('game_id', $game->id)->where('season', self::PREV_SEASON)
            ->update(['net_transfer_result' => 60_000_000_00]);
        GameFinances::create(['game_id' => $game->id, 'season' => 2024, 'net_transfer_result' => 40_000_000_00]);
        GameFinances::create(['game_id' => $game->id, 'season' => 2023, 'net_transfer_result' => 50_000_000_00]);

        $finances = $service->generateProjections($game);

        $this->assertSame(50_000_000_00, $finances->projected_trading_allowance);

        // Invariant: the allowance widens the cap base ONLY — it must never leak
        // into the projected surplus/budget.
        $this->assertSame(
            $finances->projected_total_revenue - $finances->projected_wages - $finances->projected_operating_expenses,
            $finances->projected_surplus,
        );
    }

    public function test_legacy_seasons_with_null_net_result_do_not_dilute_the_average(): void
    {
        // Disable the recurring-revenue guard so the raw average is observable.
        config(['finances.trading_allowance.max_fraction_of_recurring' => 1000.0]);

        [$service, $game] = $this->buildScenario();

        // An existing save: one freshly-settled season nets +€60M, but the two
        // older rows predate the feature and carry NULL net_transfer_result.
        // Those must be skipped, not counted as break-even zeros — otherwise the
        // average would collapse to (€60M + 0 + 0) / 3 = €20M.
        GameFinances::where('game_id', $game->id)->where('season', self::PREV_SEASON)
            ->update(['net_transfer_result' => 60_000_000_00]);
        GameFinances::create(['game_id' => $game->id, 'season' => 2024, 'net_transfer_result' => null]);
        GameFinances::create(['game_id' => $game->id, 'season' => 2023, 'net_transfer_result' => null]);

        $finances = $service->generateProjections($game);

        $this->assertSame(60_000_000_00, $finances->projected_trading_allowance);
    }

    public function test_net_buyers_get_no_trading_allowance(): void
    {
        [$service, $game] = $this->buildScenario();

        GameFinances::where('game_id', $game->id)->where('season', self::PREV_SEASON)
            ->update(['net_transfer_result' => -80_000_000_00]); // bought more than sold

        $finances = $service->generateProjections($game);

        $this->assertSame(0, $finances->projected_trading_allowance);
    }

    public function test_trading_allowance_is_capped_by_the_recurring_revenue_guard(): void
    {
        [$service, $game] = $this->buildScenario();

        // An absurd net-selling history that would dwarf recurring revenue.
        GameFinances::where('game_id', $game->id)->where('season', self::PREV_SEASON)
            ->update(['net_transfer_result' => 500_000_000_00]);

        $finances = $service->generateProjections($game);

        $guard = (int) round($finances->projected_total_revenue * 0.50);
        $this->assertSame($guard, $finances->projected_trading_allowance);
    }

    public function test_fresh_club_has_no_trading_allowance(): void
    {
        [$service, $game] = $this->buildScenario();

        GameFinances::where('game_id', $game->id)->where('season', self::PREV_SEASON)
            ->update(['net_transfer_result' => 60_000_000_00]);

        $finances = $service->generateProjections($game, freshClub: true);

        $this->assertSame(0, $finances->projected_trading_allowance);
    }

    public function test_operating_expenses_are_a_revenue_proportional_fraction(): void
    {
        [$service, $game] = $this->buildScenario();

        $finances = $service->generateProjections($game, freshClub: true);

        // Opex is a reputation-keyed fraction of the club's own PRE-subsidy
        // revenue (default reputation here is 'local'), not a flat per-tier
        // constant. Reconstruct the pre-subsidy revenue the ratio is applied to.
        $preSubsidyRevenue = $finances->projected_total_revenue - $finances->projected_subsidy_revenue;
        $expected = (int) round($preSubsidyRevenue * config('finances.opex_revenue_ratio.local'));

        $this->assertSame($expected, $finances->projected_operating_expenses);
    }

    public function test_mid_season_sale_sheds_the_prorated_remaining_wage_from_the_projection(): void
    {
        [$service, $game] = $this->buildScenario();

        // 10 league matchdays, 4 already played → 6/10 of the season unplayed.
        $finances = $this->seedCurrentSeasonProjection($game, wages: 100_000_00, surplus: 30_000_00);
        $this->seedLeagueMatchdays($game, total: 10, played: 4);

        // Selling a €5M/yr earner at this point removes 6/10 of his wage.
        $service->adjustProjectedWagesForSquadChange($game, 5_000_000_00, isArrival: false);

        $expectedDelta = (int) round(5_000_000_00 * 0.6);
        $finances->refresh();
        $this->assertSame(100_000_00 - $expectedDelta, $finances->projected_wages);
        // Surplus moves opposite to the wage bill: a wage cut lifts it.
        $this->assertSame(30_000_00 + $expectedDelta, $finances->projected_surplus);
    }

    public function test_mid_season_purchase_adds_the_prorated_remaining_wage_to_the_projection(): void
    {
        [$service, $game] = $this->buildScenario();

        $finances = $this->seedCurrentSeasonProjection($game, wages: 100_000_00, surplus: 30_000_00);
        $this->seedLeagueMatchdays($game, total: 10, played: 4);

        $service->adjustProjectedWagesForSquadChange($game, 5_000_000_00, isArrival: true);

        $expectedDelta = (int) round(5_000_000_00 * 0.6);
        $finances->refresh();
        $this->assertSame(100_000_00 + $expectedDelta, $finances->projected_wages);
        $this->assertSame(30_000_00 - $expectedDelta, $finances->projected_surplus);
    }

    public function test_adjustment_is_a_no_op_once_the_season_matchdays_are_spent(): void
    {
        [$service, $game] = $this->buildScenario();

        $finances = $this->seedCurrentSeasonProjection($game, wages: 100_000_00, surplus: 30_000_00);
        $this->seedLeagueMatchdays($game, total: 10, played: 10);

        $service->adjustProjectedWagesForSquadChange($game, 5_000_000_00, isArrival: false);

        $finances->refresh();
        $this->assertSame(100_000_00, $finances->projected_wages);
        $this->assertSame(30_000_00, $finances->projected_surplus);
    }

    private function seedCurrentSeasonProjection(Game $game, int $wages, int $surplus): GameFinances
    {
        return GameFinances::create([
            'game_id' => $game->id,
            'season' => (int) $game->season,
            'projected_wages' => $wages,
            'projected_surplus' => $surplus,
        ]);
    }

    private function seedLeagueMatchdays(Game $game, int $total, int $played): void
    {
        for ($round = 1; $round <= $total; $round++) {
            GameMatch::factory()
                ->forGame($game)
                ->forCompetition($game->competition)
                ->inRound($round)
                ->between($game->team, Team::factory()->create())
                ->create(['played' => $round <= $played]);
        }
    }

    /**
     * @return array{0: BudgetProjectionService, 1: Game, 2: Team}
     */
    private function buildScenario(): array
    {
        $team = Team::factory()->create(['stadium_seats' => 30_000]);
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()
            ->forTeam($team)
            ->inCompetition($competition->id)
            ->create([
                'season' => self::CUR_SEASON,
                'game_mode' => Game::MODE_CAREER_PRO,
            ]);

        GameFinances::create([
            'game_id' => $game->id,
            'season' => self::PREV_SEASON,
            'actual_surplus' => self::ACTUAL_SURPLUS,
            'carried_surplus' => self::CARRIED_SURPLUS,
            'carried_debt' => 0,
            'actual_commercial_revenue' => self::ACTUAL_COMMERCIAL_REVENUE,
            'actual_total_revenue' => self::ACTUAL_TOTAL_REVENUE,
        ]);

        BudgetLoan::create([
            'game_id' => $game->id,
            'season' => self::PREV_SEASON,
            'amount' => self::LOAN_REPAYMENT,
            'interest_rate' => 0,
            'repayment_amount' => self::LOAN_REPAYMENT,
            'status' => BudgetLoan::STATUS_REPAID,
        ]);

        $squadService = Mockery::mock(SquadService::class);
        $squadService->shouldReceive('calculateLeagueStrengths')->andReturn([]);
        $squadService->shouldReceive('getProjectedPosition')->andReturn(10);

        $seasonTicketPricingService = Mockery::mock(SeasonTicketPricingService::class);
        $seasonTicketPricingService->shouldReceive('soldSeasonTicketsForGame')->andReturn(0);
        $seasonTicketPricingService->shouldReceive('getCurrent')->andReturn(null);

        $stadiumLoanService = Mockery::mock(StadiumLoanService::class);
        $stadiumLoanService->shouldReceive('activePaymentsForGame')->andReturn(0);

        // No active naming-rights deal in these scenarios.
        $namingRightsService = Mockery::mock(NamingRightsService::class);
        $namingRightsService->shouldReceive('projectedRevenueForGame')->andReturn(0);

        // MatchAttendanceService is unused on this path — no league home
        // matches are scheduled, so calculateMatchdayRevenue returns 0
        // before touching it. shouldIgnoreMissing() guards future changes.
        $matchAttendanceService = Mockery::mock(MatchAttendanceService::class)->shouldIgnoreMissing(0);

        $service = new BudgetProjectionService(
            $squadService,
            $matchAttendanceService,
            $seasonTicketPricingService,
            $stadiumLoanService,
            $namingRightsService,
        );

        return [$service, $game, $team];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
