<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\Team;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Services\SeasonGoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BudgetProjectionProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_season_goal_and_stores_projections_metadata(): void
    {
        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()
            ->forTeam($team)
            ->inCompetition($competition->id)
            ->create();

        $seasonGoalService = Mockery::mock(SeasonGoalService::class);
        $seasonGoalService->shouldReceive('determineGoalForTeam')
            ->once()
            ->with(Mockery::on(fn (Team $t) => $t->id === $team->id), Mockery::any(), Mockery::any(), false)
            ->andReturn('champion');

        $finances = new GameFinances([
            'projected_position' => 3,
            'projected_total_revenue' => 100_000_000_00,
            'projected_wages' => 40_000_000_00,
            'projected_surplus' => 5_000_000_00,
            'carried_debt' => 0,
            'carried_surplus' => 0,
            'available_surplus' => 5_000_000_00,
        ]);

        $projectionService = Mockery::mock(BudgetProjectionService::class);
        $projectionService->shouldReceive('generateProjections')
            ->once()
            ->andReturn($finances);

        $processor = new BudgetProjectionProcessor($projectionService, $seasonGoalService);

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $competition->id,
        );

        $result = $processor->process($game, $data);

        $this->assertSame('champion', $game->fresh()->season_goal);

        $projections = $result->getMetadata('new_season_projections');
        $this->assertIsArray($projections);
        $this->assertSame(3, $projections['projected_position']);
        $this->assertSame(100_000_000_00, $projections['projected_total_revenue']);
        $this->assertSame('champion', $projections['season_goal']);
    }

    public function test_passes_recently_promoted_flag_when_team_was_promoted(): void
    {
        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()
            ->forTeam($team)
            ->inCompetition($competition->id)
            ->create();

        $seasonGoalService = Mockery::mock(SeasonGoalService::class);
        $seasonGoalService->shouldReceive('determineGoalForTeam')
            ->once()
            ->with(Mockery::any(), Mockery::any(), Mockery::any(), true)
            ->andReturn('survival');

        $projectionService = Mockery::mock(BudgetProjectionService::class);
        $projectionService->shouldReceive('generateProjections')
            ->andReturn(new GameFinances([
                'projected_position' => 18,
                'projected_total_revenue' => 50_000_000_00,
                'projected_wages' => 20_000_000_00,
                'projected_surplus' => 1_000_000_00,
                'carried_debt' => 0,
                'carried_surplus' => 0,
                'available_surplus' => 1_000_000_00,
            ]));

        $processor = new BudgetProjectionProcessor($projectionService, $seasonGoalService);

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $competition->id,
        );
        $data->setMetadata('promotedTeams', [['teamId' => $team->id]]);

        $processor->process($game, $data);

        $this->assertSame('survival', $game->fresh()->season_goal);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
