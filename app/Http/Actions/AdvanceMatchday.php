<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Jobs\ProcessMatchdayAdvance;

class AdvanceMatchday
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // If the user is in fast mode, the normal advance path is disabled —
        // they must explicitly exit fast mode (or use the fast-mode advance
        // route) to play matches.
        if ($game->isFastMode()) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        // Atomically claim the advancing flag. If another request already holds
        // it (concurrent click), fall through to show-game which will render
        // the loading screen for the in-flight advance.
        $updated = Game::where('id', $gameId)
            ->whereNull('matchday_advancing_at')
            ->whereNull('career_actions_processing_at')
            ->update(['matchday_advancing_at' => now(), 'matchday_advance_result' => null]);

        if ($updated) {
            ProcessMatchdayAdvance::dispatch($gameId);
        }

        // ShowGame renders the game-loading-matchday view while the job runs,
        // then consumes matchday_advance_result and redirects to the live-match
        // screen (or season-end / pending action) once the job completes.
        return redirect()->route('show-game', $gameId);
    }
}
