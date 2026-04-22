<?php

namespace Tests\Feature;

use App\Events\SeasonCompleted;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Jobs\ProcessMatchdayAdvance;
use App\Modules\Match\Services\MatchdayOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class ProcessMatchdayAdvanceTest extends TestCase
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

        $this->runSync();

        Event::assertDispatched(SeasonCompleted::class);
    }

    public function test_dispatches_season_completed_when_done_and_no_unplayed_matches_remain(): void
    {
        $this->createMatch(played: true);
        $this->mockOrchestrator(MatchdayAdvanceResult::done());
        Event::fake([SeasonCompleted::class]);

        $this->runSync();

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

        $this->runSync();

        Event::assertNotDispatched(SeasonCompleted::class);
    }

    public function test_does_not_dispatch_season_completed_for_live_match_result(): void
    {
        $this->mockOrchestrator(MatchdayAdvanceResult::liveMatch('match-id'));
        Event::fake([SeasonCompleted::class]);

        $this->runSync();

        Event::assertNotDispatched(SeasonCompleted::class);
    }

    public function test_does_not_dispatch_season_completed_for_blocked_result(): void
    {
        $this->mockOrchestrator(MatchdayAdvanceResult::blocked(null));
        Event::fake([SeasonCompleted::class]);

        $this->runSync();

        Event::assertNotDispatched(SeasonCompleted::class);
    }

    public function test_returns_null_when_advancing_flag_not_set(): void
    {
        // Job bails immediately if the caller didn't claim the flag first —
        // protects against jobs dispatched out of band from running the
        // orchestrator on a game that isn't meant to advance.
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldNotReceive('advance');
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        $this->game->update(['matchday_advancing_at' => null]);

        $this->assertNull($this->runHandle());
    }

    public function test_clears_advancing_flag_when_orchestrator_throws(): void
    {
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldReceive('advance')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        $this->game->update(['matchday_advancing_at' => now()]);

        try {
            $this->runHandle();
            $this->fail('expected RuntimeException to propagate');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNull($this->game->refresh()->matchday_advancing_at);
    }

    public function test_stores_result_on_game_for_show_game_to_consume(): void
    {
        $this->mockOrchestrator(MatchdayAdvanceResult::liveMatch('match-id'));

        $this->game->update(['matchday_advancing_at' => now()]);
        $this->runHandle();

        $this->game->refresh();
        $this->assertSame('live_match', $this->game->matchday_advance_result['type']);
        $this->assertSame('match-id', $this->game->matchday_advance_result['matchId']);
        $this->assertNull($this->game->matchday_advancing_at);
    }

    public function test_passes_fast_forward_flag_to_orchestrator(): void
    {
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldReceive('advance')
            ->once()
            ->with(Mockery::type(Game::class), true)
            ->andReturn(MatchdayAdvanceResult::done());
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        $this->game->update(['matchday_advancing_at' => now()]);

        $result = $this->runHandle(fastForward: true);

        $this->assertSame('done', $result?->type);
    }

    // See MatchdayAdvanceCoordinator::runSync for why handle() is invoked
    // directly instead of via Bus::dispatchSync.
    private function runHandle(bool $fastForward = false): ?MatchdayAdvanceResult
    {
        $job = new ProcessMatchdayAdvance($this->game->id, $fastForward);

        return $this->app->call([$job, 'handle']);
    }

    private function runSync(): ?MatchdayAdvanceResult
    {
        $this->game->update(['matchday_advancing_at' => now()]);

        return $this->runHandle();
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
