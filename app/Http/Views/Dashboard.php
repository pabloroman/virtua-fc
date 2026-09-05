<?php

namespace App\Http\Views;

use App\Models\Game;
use Illuminate\Http\Request;

class Dashboard
{
    public function __construct()
    {
    }

    public function __invoke(Request $request)
    {
        $games = Game::with('team')->where('user_id', $request->user()->id)->whereNull('deleting_at')->get();

        if (! $games->count()) {
            return redirect()->route('select-team');
        }

        $maxGames = 3;

        return view('dashboard', [
            'user' => $request->user(),
            'games' => $games,
            'canCreateGame' => $games->count() < $maxGames,
            // Only users who already have a career from an older data season
            // need telling that saves keep the squads they started with.
            'hasLegacySaves' => $games->contains(
                fn (Game $game) => ! $game->isTournamentMode() && $game->isFromPastBaseSeason()
            ),
            'gameCount' => $games->count(),
            'maxGames' => $maxGames,
        ]);
    }
}
