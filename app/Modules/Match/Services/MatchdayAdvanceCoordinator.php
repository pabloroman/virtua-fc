<?php

namespace App\Modules\Match\Services;

use App\Models\Game;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Jobs\ProcessMatchdayAdvance;
use Illuminate\Support\Facades\Bus;

/**
 * Owns the atomic "claim the advancing flag + dispatch the job" dance. Every
 * caller that advances a matchday goes through here so the flag check, the
 * fast-mode guard, and the decision between async and sync dispatch live in
 * one place.
 *
 * The actual advancing work (orchestrator call, SeasonCompleted, activation
 * tracking, flag cleanup) lives in ProcessMatchdayAdvance.
 */
class MatchdayAdvanceCoordinator
{
    /**
     * Claim the flag and dispatch the job to the queue. Returns true when the
     * flag was claimed, false when another request already holds it (the
     * caller typically just redirects to the in-flight loading screen).
     */
    public function dispatchAsync(string $gameId): bool
    {
        if (! $this->claim($gameId, fastForward: false)) {
            return false;
        }

        ProcessMatchdayAdvance::dispatch($gameId);

        return true;
    }

    /**
     * Claim the flag and run the job inline in the current process. Used by
     * fast mode (no live UI to defer to) and console commands (no queue
     * worker assumed). Returns null when the flag couldn't be claimed.
     */
    public function runSync(string $gameId, bool $fastForward = false): ?MatchdayAdvanceResult
    {
        if (! $this->claim($gameId, $fastForward)) {
            return null;
        }

        return Bus::dispatchSync(new ProcessMatchdayAdvance($gameId, $fastForward));
    }

    /**
     * Atomic check-and-set on matchday_advancing_at. The fast-forward path
     * additionally requires fast_mode_entered_on to be set so a double-submit
     * racing an ExitFastMode action can't fast-forward a game that's no
     * longer in fast mode.
     */
    private function claim(string $gameId, bool $fastForward): bool
    {
        return (bool) Game::where('id', $gameId)
            ->whereNull('matchday_advancing_at')
            ->whereNull('career_actions_processing_at')
            ->when($fastForward, fn ($q) => $q->whereNotNull('fast_mode_entered_on'))
            ->update(['matchday_advancing_at' => now(), 'matchday_advance_result' => null]);
    }
}
