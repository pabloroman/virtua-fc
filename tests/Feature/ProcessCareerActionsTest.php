<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Modules\Match\Services\CareerActionProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ProcessCareerActionsTest extends TestCase
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

    public function test_enqueue_increments_counter_and_dispatches_when_not_already_running(): void
    {
        Queue::fake();

        ProcessCareerActions::enqueue($this->game->id, 3);

        $this->game->refresh();
        $this->assertSame(3, $this->game->pending_career_action_ticks);
        $this->assertNotNull($this->game->career_actions_processing_at);
        Queue::assertPushed(
            ProcessCareerActions::class,
            fn ($job) => $job->gameId === $this->game->id,
        );
    }

    public function test_enqueue_accumulates_ticks_without_redispatch_when_job_is_running(): void
    {
        Queue::fake();

        ProcessCareerActions::enqueue($this->game->id, 2);
        ProcessCareerActions::enqueue($this->game->id, 5);

        $this->game->refresh();
        $this->assertSame(7, $this->game->pending_career_action_ticks);
        Queue::assertPushed(ProcessCareerActions::class, 1);
    }

    public function test_enqueue_is_a_noop_for_non_positive_ticks(): void
    {
        Queue::fake();

        ProcessCareerActions::enqueue($this->game->id, 0);
        ProcessCareerActions::enqueue($this->game->id, -1);

        $this->game->refresh();
        $this->assertSame(0, $this->game->pending_career_action_ticks);
        $this->assertNull($this->game->career_actions_processing_at);
        Queue::assertNothingPushed();
    }

    public function test_handle_drains_all_pending_ticks(): void
    {
        $processor = Mockery::mock(CareerActionProcessor::class);
        $processor->shouldReceive('process')->times(3);
        $this->app->instance(CareerActionProcessor::class, $processor);

        $this->game->update([
            'career_actions_processing_at' => now(),
            'pending_career_action_ticks' => 3,
        ]);

        $job = new ProcessCareerActions($this->game->id);
        $this->app->call([$job, 'handle']);

        $this->game->refresh();
        $this->assertSame(0, $this->game->pending_career_action_ticks);
        $this->assertNull($this->game->career_actions_processing_at);
    }

    public function test_handle_picks_up_ticks_added_mid_drain(): void
    {
        // Regression guard: enqueueing more ticks while the job is running
        // must not get lost — the drain loop rechecks the counter each tick.
        $game = $this->game;
        $processor = Mockery::mock(CareerActionProcessor::class);
        $callCount = 0;
        $processor->shouldReceive('process')->andReturnUsing(function () use (&$callCount, $game) {
            $callCount++;
            if ($callCount === 1) {
                // Simulate a concurrent enqueue during the first tick.
                Game::where('id', $game->id)->increment('pending_career_action_ticks', 2);
            }
        });
        $this->app->instance(CareerActionProcessor::class, $processor);

        $this->game->update([
            'career_actions_processing_at' => now(),
            'pending_career_action_ticks' => 1,
        ]);

        $job = new ProcessCareerActions($this->game->id);
        $this->app->call([$job, 'handle']);

        // 1 original + 2 added mid-flight = 3 ticks processed.
        $this->assertSame(3, $callCount);
        $this->game->refresh();
        $this->assertSame(0, $this->game->pending_career_action_ticks);
        $this->assertNull($this->game->career_actions_processing_at);
    }

    public function test_handle_bails_when_flag_not_set(): void
    {
        // Belt-and-suspenders guard against jobs dispatched without a prior
        // enqueue() claim (e.g. a stale queue entry after the flag was
        // cleared by clearStuckCareerActions).
        $processor = Mockery::mock(CareerActionProcessor::class);
        $processor->shouldNotReceive('process');
        $this->app->instance(CareerActionProcessor::class, $processor);

        $this->game->update([
            'career_actions_processing_at' => null,
            'pending_career_action_ticks' => 3,
        ]);

        $job = new ProcessCareerActions($this->game->id);
        $this->app->call([$job, 'handle']);

        $this->game->refresh();
        $this->assertSame(3, $this->game->pending_career_action_ticks);
    }

    public function test_handle_clears_flag_when_counter_is_zero(): void
    {
        // Safety path: if the flag was set but no ticks were added, the
        // drain loop must release the flag rather than spin forever.
        $processor = Mockery::mock(CareerActionProcessor::class);
        $processor->shouldNotReceive('process');
        $this->app->instance(CareerActionProcessor::class, $processor);

        $this->game->update([
            'career_actions_processing_at' => now(),
            'pending_career_action_ticks' => 0,
        ]);

        $job = new ProcessCareerActions($this->game->id);
        $this->app->call([$job, 'handle']);

        $this->game->refresh();
        $this->assertNull($this->game->career_actions_processing_at);
    }

    public function test_failed_clears_flag_and_counter(): void
    {
        $this->game->update([
            'career_actions_processing_at' => now(),
            'pending_career_action_ticks' => 4,
        ]);

        $job = new ProcessCareerActions($this->game->id);
        $job->failed(new \RuntimeException('boom'));

        $this->game->refresh();
        $this->assertNull($this->game->career_actions_processing_at);
        $this->assertSame(0, $this->game->pending_career_action_ticks);
    }
}
