<?php

namespace Tests\Feature;

use App\Events\SeasonCompleted;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use App\Modules\Match\Services\MatchdayOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class MatchdayAdvanceCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $playerTeam;
    private Team $opponentTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->playerTeam = Team::factory()->create();
        $this->opponentTeam = Team::factory()->create();
        $competition = Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $competition->id,
        ]);
    }

    public function test_dispatches_season_completed_when_orchestrator_returns_season_complete(): void
    {
        $this->mockOrchestrator(MatchdayAdvanceResult::seasonComplete());
        Event::fake([SeasonCompleted::class]);

        $this->coordinator()->advance($this->game->id);

        Event::assertDispatched(SeasonCompleted::class);
    }

    public function test_dispatches_season_completed_when_done_and_no_unplayed_matches_remain(): void
    {
        $this->createMatch(played: true);
        $this->mockOrchestrator(MatchdayAdvanceResult::done());
        Event::fake([SeasonCompleted::class]);

        $this->coordinator()->advance($this->game->id);

        Event::assertDispatched(SeasonCompleted::class);
    }

    public function test_does_not_dispatch_season_completed_when_done_and_unplayed_matches_remain(): void
    {
        // Regression guard: the orchestrator returns `done` on every fast-mode
        // click. Without the gate, SeasonCompleted listeners (other-leagues
        // sim, season-completed activation event) fire prematurely.
        $this->createMatch(played: true);
        $this->createMatch(played: false);
        $this->mockOrchestrator(MatchdayAdvanceResult::done());
        Event::fake([SeasonCompleted::class]);

        $this->coordinator()->advance($this->game->id);

        Event::assertNotDispatched(SeasonCompleted::class);
    }

    public function test_does_not_dispatch_season_completed_for_live_match_result(): void
    {
        $this->mockOrchestrator(MatchdayAdvanceResult::liveMatch('match-id'));
        Event::fake([SeasonCompleted::class]);

        $this->coordinator()->advance($this->game->id);

        Event::assertNotDispatched(SeasonCompleted::class);
    }

    public function test_does_not_dispatch_season_completed_for_blocked_result(): void
    {
        $this->mockOrchestrator(MatchdayAdvanceResult::blocked(null));
        Event::fake([SeasonCompleted::class]);

        $this->coordinator()->advance($this->game->id);

        Event::assertNotDispatched(SeasonCompleted::class);
    }

    public function test_returns_null_when_advancing_flag_already_held(): void
    {
        $this->game->update(['matchday_advancing_at' => now()]);

        // Orchestrator must not be called when the check-and-set fails.
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldNotReceive('advance');
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        $this->assertNull($this->coordinator()->advance($this->game->id));
    }

    public function test_fast_forward_requires_fast_mode_entered_on_to_be_set(): void
    {
        // Game is not in fast mode, so the conditional whereNotNull guard
        // prevents the check-and-set from succeeding.
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldNotReceive('advance');
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        $this->assertNull($this->coordinator()->advance($this->game->id, fastForward: true));
    }

    public function test_clears_advancing_flag_when_orchestrator_throws(): void
    {
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldReceive('advance')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        try {
            $this->coordinator()->advance($this->game->id);
            $this->fail('expected RuntimeException to propagate');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNull($this->game->refresh()->matchday_advancing_at);
    }

    private function coordinator(): MatchdayAdvanceCoordinator
    {
        return app(MatchdayAdvanceCoordinator::class);
    }

    private function mockOrchestrator(MatchdayAdvanceResult $result): void
    {
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldReceive('advance')->andReturn($result);
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);
    }

    private function createMatch(bool $played): GameMatch
    {
        return GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'played' => $played,
        ]);
    }
}
