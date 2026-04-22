<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Jobs\ProcessMatchdayAdvance;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class AdvanceFastMatchday
{
    public function __invoke(string $gameId)
    {
        // Atomic flag claim. The `whereNotNull('fast_mode_entered_on')` guard
        // ensures fast-forward can't be invoked on a game that isn't in fast
        // mode (double-submit racing an ExitFastMode action).
        $updated = Game::where('id', $gameId)
            ->whereNull('matchday_advancing_at')
            ->whereNull('career_actions_processing_at')
            ->whereNotNull('fast_mode_entered_on')
            ->update(['matchday_advancing_at' => now(), 'matchday_advance_result' => null]);

        if (! $updated) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        try {
            // Run inline — fast mode has no live UI to defer to, so the user's
            // click blocks until the matchday is resolved.
            $result = Bus::dispatchSync(new ProcessMatchdayAdvance($gameId, fastForward: true));
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

        // Consume the stored result so a later visit to ShowGame (e.g. if the
        // user exits fast mode) doesn't react to a stale advance outcome.
        $game = Game::findOrFail($gameId);
        $game->update(['matchday_advance_result' => null]);

        return match ($result->type) {
            // Fast mode never returns live_match (the orchestrator finalizes
            // the user's match inline); included only to satisfy match
            // exhaustiveness. `blocked` only reaches here on the transient
            // career-actions-in-progress race, which ShowFastMode handles.
            'live_match', 'done', 'blocked' => redirect()->route('game.fast-mode', $gameId),
            'season_complete' => redirect()->route(
                $game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end',
                $gameId,
            ),
        };
    }
}
