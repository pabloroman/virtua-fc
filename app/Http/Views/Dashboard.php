<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\TournamentSummary;
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

        $tournamentHistory = TournamentSummary::with(['team', 'competition'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('dashboard', [
            'user' => $request->user(),
            'games' => $games,
            'canCreateGame' => $games->count() < $maxGames,
            'gameCount' => $games->count(),
            'maxGames' => $maxGames,
            'tournamentHistory' => $tournamentHistory,
        ]);
    }
}
