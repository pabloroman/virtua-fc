<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use Illuminate\Support\Facades\Log;

class AdvanceFastMatchday
{
    public function __construct(
        private readonly MatchdayAdvanceCoordinator $coordinator,
    ) {}

    public function __invoke(string $gameId)
    {
        try {
            $result = $this->coordinator->runSync($gameId, fastForward: true);
        } catch (\Throwable $e) {
            Log::error('Fast-mode matchday advance failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('game.fast-mode', $gameId)
                ->with('error', __('messages.advance_failed'));
        }

        if (! $result) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        // Clear the stored result so ShowFastMode (which treats a non-null
        // result as a "busy" signal) doesn't bounce the next poll.
        $game = Game::findOrFail($gameId);
        $game->update(['matchday_advance_result' => null]);

        return match ($result->type) {
            // Fast mode never returns live_match (the orchestrator finalizes
            // the user's match inline); included only to satisfy match
            // exhaustiveness. `blocked` only reaches here on the transient
            // career-actions-in-progress race, which ShowFastMode handles.
            'live_match', 'done', 'blocked' => redirect()->route('game.fast-mode', $gameId),
            // Tournament mode goes straight to tournament-end. Season-based
            // modes return to fast-mode so the user can see the score of the
            // just-simulated final match before being offered a Continue CTA
            // (handled by fast-mode.blade.php when $nextMatch is null).
            'season_complete' => $game->isTournamentMode()
                ? redirect()->route('game.tournament-end', $gameId)
                : redirect()->route('game.fast-mode', $gameId),
        };
    }
}
