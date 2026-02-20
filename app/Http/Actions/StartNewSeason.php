<?php

namespace App\Http\Actions;

use App\Modules\Season\Jobs\ProcessSeasonTransition;
use App\Models\Game;

class StartNewSeason
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Verify season is complete
        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('messages.season_not_complete'));
        }

        // Prevent double-dispatch
        if ($game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Mark as transitioning and dispatch background job
        $game->update(['season_transitioning_at' => now()]);

        ProcessSeasonTransition::dispatch($game->id);

        return redirect()->route('show-game', $gameId);
    }
}
