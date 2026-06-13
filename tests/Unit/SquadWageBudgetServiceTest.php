<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Finance\Services\SquadWageBudgetService;
use App\Modules\Finance\Services\WageModelService;
use App\Modules\Squad\Services\SquadService;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * SquadWageBudgetService assigns each seeded squad a wage bill it can afford —
 * wage = playerWeight × clubWageLevel — so the projected surplus, and the
 * transfer budget, come out realistic. The seeded wage acts as the player's
 * relative weight, so the intra-squad distribution must survive untouched.
 */
class SquadWageBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_bill_to_revenue_ratio_and_preserves_distribution(): void
    {
        [$service, $game, $team, $competition] = $this->buildScenario(
            wageBudgetRevenue: 100_000_000_00, // €100M revenue base
            reputation: ClubProfile::REPUTATION_ESTABLISHED, // ratio 0.60
            wages: [1_000_000_00, 2_000_000_00, 3_000_000_00], // €1M / €2M / €3M, bill €6M
        );

        $service->assignTeamWageBudget($game, $team, $competition);

        $wages = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->orderBy('annual_wage')
            ->pluck('annual_wage')
            ->all();

        $target = (int) round(100_000_000_00 * config('finances.wage_revenue_ratio.established'));
        $this->assertEqualsWithDelta($target, array_sum($wages), 1_000_00); // within €1k of €60M

        // 1 : 2 : 3 distribution is preserved (every weight scaled by one level).
        $this->assertEqualsWithDelta(2.0, $wages[1] / $wages[0], 0.001);
        $this->assertEqualsWithDelta(3.0, $wages[2] / $wages[0], 0.001);
    }

    /**
     * Equivalence guard: assigning weight × clubWageLevel must reproduce, to the
     * cent, the old single-factor normalization (round(revenue × ratio) ÷ bill,
     * applied per player, floored). If this drifts, the "behavior-preserving"
     * claim of the wage-model refactor is broken.
     */
    public function test_wage_equals_weight_times_club_level(): void
    {
        $revenue = 100_000_000_00; // €100M
        $wages = [1_000_000_00, 2_000_000_00, 3_000_000_00]; // €1M / €2M / €3M
        $minWage = 0;

        [$service, $game, $team, $competition] = $this->buildScenario(
            wageBudgetRevenue: $revenue,
            reputation: ClubProfile::REPUTATION_ESTABLISHED, // ratio 0.60
            wages: $wages,
            minWage: $minWage,
        );

        // Reproduce the legacy factor math independently.
        $ratio = (float) config('finances.wage_revenue_ratio.established');
        $targetBill = (int) round($revenue * $ratio);
        $level = $targetBill / array_sum($wages);
        $expected = array_map(
            fn (int $w) => max((int) (round($w * $level / 1_000_000) * 1_000_000), $minWage),
            $wages,
        );
        sort($expected);

        $service->assignTeamWageBudget($game, $team, $competition);

        $actual = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->orderBy('annual_wage')
            ->pluck('annual_wage')
            ->all();

        $this->assertSame($expected, array_map('intval', $actual));
    }

    public function test_never_drops_a_wage_below_the_league_minimum_when_scaling_down(): void
    {
        // Tiny revenue base → target bill far below the current bill, so the
        // club wage level is < 1. No wage may fall under the league minimum.
        $minWage = 10_000_00; // €10k
        [$service, $game, $team, $competition] = $this->buildScenario(
            wageBudgetRevenue: 1_000_000_00, // €1M base → target €0.6M
            reputation: ClubProfile::REPUTATION_LOCAL,
            wages: [5_000_000_00, 5_000_000_00], // €5M each, bill €10M
            minWage: $minWage,
        );

        $service->assignTeamWageBudget($game, $team, $competition);

        $min = GamePlayer::where('game_id', $game->id)->where('team_id', $team->id)->min('annual_wage');
        $this->assertGreaterThanOrEqual($minWage, $min);
    }

    /**
     * @param  array<int, int>  $wages
     * @return array{0: SquadWageBudgetService, 1: Game, 2: Team, 3: Competition}
     */
    private function buildScenario(int $wageBudgetRevenue, string $reputation, array $wages, int $minWage = 0): array
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
        $budgetProjection->shouldReceive('wageBudgetRevenueForTeam')->andReturn($wageBudgetRevenue);

        $contractService = Mockery::mock(ContractService::class);
        $contractService->shouldReceive('getMinimumWageForClub')->andReturn($minWage);

        // SquadService is only used by assignWageBudget() (to rank a whole league),
        // not by assignTeamWageBudget() when a position is already known / unused here.
        $squadService = Mockery::mock(SquadService::class);

        $service = new SquadWageBudgetService(
            new WageModelService($budgetProjection, $contractService),
            $squadService,
            $contractService,
        );

        return [$service, $game, $team, $competition];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
