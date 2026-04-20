<?php

namespace App\Modules\Match\Jobs;

use App\Models\Game;
use App\Modules\Match\Services\CareerActionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCareerActions implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function __construct(
        public string $gameId,
        public int $ticks,
    ) {
        $this->onQueue('gameplay');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function handle(CareerActionProcessor $processor): void
    {
        // Each tick runs inside its own transaction holding the game row lock.
        // This serializes career actions against matchday advancement, the
        // ProcessRemainingBatches job, and FinalizeMatch — all of which write
        // to the same game_player_match_state / game_players rows and would
        // otherwise deadlock at the PK/FK index level.
        //
        // Per-tick (rather than whole-job) locking keeps the critical section
        // short so a user advancing to the next matchday is only ever blocked
        // by a single tick's work, not the full accumulated batch.
        for ($i = 0; $i < $this->ticks; $i++) {
            $processed = DB::transaction(function () use ($processor) {
                $game = Game::where('id', $this->gameId)->lockForUpdate()->first();

                if (! $game || ! $game->isProcessingCareerActions()) {
                    return false;
                }

                $processor->process($game);

                return true;
            });

            if (! $processed) {
                return;
            }
        }

        Game::where('id', $this->gameId)->update(['career_actions_processing_at' => null]);
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)->update(['career_actions_processing_at' => null]);

        Log::error('Career actions processing failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
