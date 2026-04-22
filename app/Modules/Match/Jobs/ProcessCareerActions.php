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

/**
 * Drains pending career-action ticks for a game (transfers, scouting ticks,
 * contract warnings, AI market, youth development, etc.). Callers use
 * ::enqueue() to atomically bump the tick counter and — if no job is in
 * flight — dispatch a new one. A single running job will keep draining until
 * the counter hits zero, so ticks added mid-flight are never lost.
 *
 * Per-tick locking (see handle()) serializes each tick against matchday
 * advancement, which takes the same row lock, so a user advancing is only
 * ever blocked by one tick's work instead of the full accumulated batch.
 */
class ProcessCareerActions implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function __construct(
        public string $gameId,
    ) {
        $this->onQueue('gameplay');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    /**
     * Atomically add $ticks to the game's pending counter. If no job is
     * currently in flight for this game, claim the processing flag and
     * dispatch one. If a job is already running, it will pick up the added
     * ticks on its next iteration.
     */
    public static function enqueue(string $gameId, int $ticks): void
    {
        if ($ticks <= 0) {
            return;
        }

        Game::where('id', $gameId)->increment('pending_career_action_ticks', $ticks);

        $claimed = (bool) Game::where('id', $gameId)
            ->whereNull('career_actions_processing_at')
            ->update(['career_actions_processing_at' => now()]);

        if (! $claimed) {
            return;
        }

        try {
            self::dispatch($gameId);
        } catch (\Throwable $e) {
            // On dispatch failure, surrender the claim and discard the
            // accumulated debt — leaving work parked with no runner would
            // permanently block future matchday advances.
            Game::where('id', $gameId)->update([
                'career_actions_processing_at' => null,
                'pending_career_action_ticks' => 0,
            ]);

            throw $e;
        }
    }

    public function handle(CareerActionProcessor $processor): void
    {
        // Drain the pending counter one tick at a time. Each tick runs in its
        // own transaction holding the game row lock; this keeps the critical
        // section short so a concurrent matchday advance only waits on one
        // tick's work, while still serializing writes to game_players /
        // game_player_match_state rows that both jobs touch.
        while (true) {
            $continue = DB::transaction(function () use ($processor) {
                $game = Game::where('id', $this->gameId)->lockForUpdate()->first();

                if (! $game || ! $game->isProcessingCareerActions()) {
                    return false;
                }

                if ($game->pending_career_action_ticks <= 0) {
                    $game->update(['career_actions_processing_at' => null]);

                    return false;
                }

                $processor->process($game);
                $game->decrement('pending_career_action_ticks');

                return true;
            });

            if (! $continue) {
                return;
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        // Reset both flag and counter on terminal failure. Leaving ticks
        // parked would cause the next successful enqueue to re-run the
        // accumulated debt against a possibly-changed game state — better to
        // drop the failed batch and resume fresh.
        Game::where('id', $this->gameId)->update([
            'career_actions_processing_at' => null,
            'pending_career_action_ticks' => 0,
        ]);

        Log::error('Career actions processing failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
