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
        $games = Game::with('team')->where('user_id', $request->user()->id)->get();

        if (! $games->count()) {
            return redirect()->route('select-team');
        }

        $maxGames = 3;

        return view('dashboard', [
            'user' => $request->user(),
            'games' => $games,
            'canCreateGame' => $games->count() < $maxGames,
            'gameCount' => $games->count(),
            'maxGames' => $maxGames,
        ]);
    }
}
