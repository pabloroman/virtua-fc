<?php

namespace Tests\Feature;

use App\Http\Actions\StartNewSeason;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Unit\FakePlayoffGenerator;

/**
 * Verifies that the season-transition guards refuse to dispatch the closing
 * pipeline while any playoff is still in progress. This is the regression
 * shield for Bug B — a player losing a playoff semifinal used to trigger
 * the transition before the final had resolved, promoting the wrong team.
 */
class SeasonTransitionGuardTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff']);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP2',
            'season' => '2025',
        ]);
    }

    public function test_start_new_season_blocks_dispatch_when_playoff_in_progress(): void
    {
        Queue::fake();

        // Replace the factory with one that reports InProgress for any
        // registered generator. We bind a fake via the container.
        $this->app->instance(PlayoffGeneratorFactory::class, new class extends PlayoffGeneratorFactory {
            public function __construct() {}
            public function all(): array
            {
                return [new FakePlayoffGenerator(PlayoffState::InProgress, 'ESP2')];
            }
        });

        $action = $this->app->make(StartNewSeason::class);
        $response = $action($this->game->id);

        // Transition flag must NOT be set and job must NOT be dispatched.
        $this->game->refresh();
        $this->assertNull($this->game->season_transitioning_at);
        Queue::assertNotPushed(ProcessSeasonTransition::class);
    }

    public function test_start_new_season_dispatches_when_all_playoffs_complete(): void
    {
        Queue::fake();

        $this->app->instance(PlayoffGeneratorFactory::class, new class extends PlayoffGeneratorFactory {
            public function __construct() {}
            public function all(): array
            {
                return [new FakePlayoffGenerator(PlayoffState::Completed, 'ESP2')];
            }
        });

        $action = $this->app->make(StartNewSeason::class);
        $action($this->game->id);

        $this->game->refresh();
        $this->assertNotNull($this->game->season_transitioning_at);
        Queue::assertPushed(ProcessSeasonTransition::class);
    }

    public function test_start_new_season_dispatches_when_no_playoffs_exist(): void
    {
        Queue::fake();

        $this->app->instance(PlayoffGeneratorFactory::class, new class extends PlayoffGeneratorFactory {
            public function __construct() {}
            public function all(): array { return []; }
        });

        $action = $this->app->make(StartNewSeason::class);
        $action($this->game->id);

        $this->game->refresh();
        $this->assertNotNull($this->game->season_transitioning_at);
        Queue::assertPushed(ProcessSeasonTransition::class);
    }
}
