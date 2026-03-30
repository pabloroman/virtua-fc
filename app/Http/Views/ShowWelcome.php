<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\Game;

class ShowWelcome
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // If welcome is already complete, go to new-season setup or game
        if (!$game->needsWelcome()) {
            if ($game->needsNewSeasonSetup()) {
                return redirect()->route('game.new-season', $gameId);
            }
            return redirect()->route('show-game', $gameId);
        }

        $competition = Competition::find($game->competition_id);

        $view = $game->isTournamentMode() ? 'welcome-tournament' : 'welcome-tutorial';

        return view($view, [
            'game' => $game,
            'competition' => $competition,
        ]);
    }
}
