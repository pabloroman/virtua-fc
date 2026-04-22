<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Jobs\ProcessMatchdayAdvance;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use App\Modules\Match\Services\MatchdayOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class MatchdayAdvanceCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
        ]);
    }

    public function test_dispatch_async_claims_flag_and_queues_job(): void
    {
        Queue::fake();

        $claimed = $this->coordinator()->dispatchAsync($this->game->id);

        $this->assertTrue($claimed);
        $this->assertNotNull($this->game->refresh()->matchday_advancing_at);
        Queue::assertPushed(
            ProcessMatchdayAdvance::class,
            fn ($job) => $job->gameId === $this->game->id && $job->fastForward === false,
        );
    }

    public function test_dispatch_async_returns_false_when_flag_already_held(): void
    {
        Queue::fake();
        $this->game->update(['matchday_advancing_at' => now()]);

        $this->assertFalse($this->coordinator()->dispatchAsync($this->game->id));
        Queue::assertNothingPushed();
    }

    public function test_run_sync_returns_result_from_job(): void
    {
        $expected = MatchdayAdvanceResult::liveMatch('match-id');
        $this->mockOrchestrator($expected);

        $result = $this->coordinator()->runSync($this->game->id);

        $this->assertSame($expected->type, $result?->type);
        $this->assertSame($expected->matchId, $result?->matchId);
    }

    public function test_run_sync_returns_null_when_flag_already_held(): void
    {
        $this->game->update(['matchday_advancing_at' => now()]);

        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldNotReceive('advance');
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        $this->assertNull($this->coordinator()->runSync($this->game->id));
    }

    public function test_run_sync_with_fast_forward_requires_fast_mode_entered_on(): void
    {
        // Game is not in fast mode, so the whereNotNull guard blocks the claim.
        $orchestrator = Mockery::mock(MatchdayOrchestrator::class);
        $orchestrator->shouldNotReceive('advance');
        $this->app->instance(MatchdayOrchestrator::class, $orchestrator);

        $this->assertNull($this->coordinator()->runSync($this->game->id, fastForward: true));
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
}
