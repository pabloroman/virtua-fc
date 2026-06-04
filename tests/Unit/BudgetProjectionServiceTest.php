<?php

namespace Tests\Unit;

use App\Models\BudgetLoan;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
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
        $seasonTicketPricingService->shouldReceive('walkupRelevantSoldForGame')->andReturn(0);
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
