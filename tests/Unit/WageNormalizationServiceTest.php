<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Finance\Services\WageNormalizationService;
use App\Modules\Squad\Services\SquadService;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * WageNormalizationService scales each seeded squad's wage bill to a realistic
 * share of the club's own revenue (config finances.wage_revenue_ratio), which is
 * what keeps the projected surplus — and the transfer budget — realistic. The
 * formula's intra-squad distribution must survive untouched.
 */
class WageNormalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scales_bill_to_revenue_ratio_and_preserves_distribution(): void
    {
        [$service, $game, $team, $competition] = $this->buildScenario(
            wageBaseRevenue: 100_000_000_00, // €100M revenue base
            reputation: ClubProfile::REPUTATION_ESTABLISHED, // ratio 0.60
            wages: [1_000_000_00, 2_000_000_00, 3_000_000_00], // €1M / €2M / €3M, bill €6M
        );

        $service->normalizeTeam($game, $team, $competition);

        $wages = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->orderBy('annual_wage')
            ->pluck('annual_wage')
            ->all();

        $target = (int) round(100_000_000_00 * config('finances.wage_revenue_ratio.established'));
        $this->assertEqualsWithDelta($target, array_sum($wages), 1_000_00); // within €1k of €60M

        // 1 : 2 : 3 distribution is preserved (every wage scaled by one factor).
        $this->assertEqualsWithDelta(2.0, $wages[1] / $wages[0], 0.001);
        $this->assertEqualsWithDelta(3.0, $wages[2] / $wages[0], 0.001);
    }

    public function test_never_drops_a_wage_below_the_league_minimum_when_scaling_down(): void
    {
        // Tiny revenue base → target bill far below the current bill, so the
        // scale factor is < 1. No wage may fall under the league minimum.
        $minWage = 10_000_00; // €10k
        [$service, $game, $team, $competition] = $this->buildScenario(
            wageBaseRevenue: 1_000_000_00, // €1M base → target €0.6M
            reputation: ClubProfile::REPUTATION_LOCAL,
            wages: [5_000_000_00, 5_000_000_00], // €5M each, bill €10M
            minWage: $minWage,
        );

        $service->normalizeTeam($game, $team, $competition);

        $min = GamePlayer::where('game_id', $game->id)->where('team_id', $team->id)->min('annual_wage');
        $this->assertGreaterThanOrEqual($minWage, $min);
    }

    /**
     * @param  array<int, int>  $wages
     * @return array{0: WageNormalizationService, 1: Game, 2: Team, 3: Competition}
     */
    private function buildScenario(int $wageBaseRevenue, string $reputation, array $wages, int $minWage = 0): array
    {
        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create(['tier' => 1]);
        $game = Game::factory()->forTeam($team)->inCompetition($competition->id)->create();

        // Reputation is resolved from the club profile when no per-game row exists.
        ClubProfile::create(['team_id' => $team->id, 'reputation_level' => $reputation]);

        foreach ($wages as $wage) {
            GamePlayer::factory()->forGame($game)->forTeam($team)->create(['annual_wage' => $wage]);
        }

        $budgetProjection = Mockery::mock(BudgetProjectionService::class);
        $budgetProjection->shouldReceive('wageBaseRevenueForTeam')->andReturn($wageBaseRevenue);

        $contractService = Mockery::mock(ContractService::class);
        $contractService->shouldReceive('getMinimumWageForClub')->andReturn($minWage);

        // SquadService is only used by normalizeGame() (to rank a whole league),
        // not by normalizeTeam() when a position is already known / unused here.
        $squadService = Mockery::mock(SquadService::class);

        $service = new WageNormalizationService($budgetProjection, $squadService, $contractService);

        return [$service, $game, $team, $competition];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
