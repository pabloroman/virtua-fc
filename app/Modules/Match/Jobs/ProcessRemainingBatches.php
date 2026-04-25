<?php

namespace App\Modules\Match\Jobs;

use App\Models\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase-1 deploy stub.
 *
 * The deferred AI-only-batch path was inlined into MatchdayOrchestrator, but
 * jobs already enqueued at deploy time still deserialize against this class.
 * Keeping the class for one deploy cycle drains those in-flight jobs without
 * losing state: clear the orphaned `remaining_batches_processing_at` flag and
 * forward any prior career-action ticks the old path was holding so the
 * affected games still tick post-match work. The unplayed AI batches are
 * picked up by getNextMatchBatch on the user's next Advance click — see the
 * inline call in MatchdayOrchestrator::advance().
 *
 * Delete this file (and remove the constructor matching against it from any
 * dispatch sites — there are none left) once the gameplay queue has fully
 * drained post-deploy.
 */
class ProcessRemainingBatches implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function __construct(
        public string $gameId,
        public int $careerActionTicks,
    ) {
        $this->onQueue('gameplay');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function handle(): void
    {
        Game::where('id', $this->gameId)
            ->update(['remaining_batches_processing_at' => null]);

        if ($this->careerActionTicks <= 0) {
            return;
        }

        $updated = Game::where('id', $this->gameId)
            ->whereNull('career_actions_processing_at')
            ->update(['career_actions_processing_at' => now()]);

        if ($updated) {
            try {
                ProcessCareerActions::dispatch($this->gameId, $this->careerActionTicks);
            } catch (\Throwable $e) {
                Game::where('id', $this->gameId)
                    ->update(['career_actions_processing_at' => null]);
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)
            ->update(['remaining_batches_processing_at' => null]);

        Log::error('Drained ProcessRemainingBatches stub failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
