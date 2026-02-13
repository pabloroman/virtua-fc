<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\Game;
use Illuminate\Http\Request;

final class SelectTeam
{
    public function __invoke(Request $request)
    {
        if (Game::where('user_id', $request->user()->id)->count() >= 3) {
            return redirect()->route('dashboard')->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $competitions = Competition::with('teams')
            ->where('role', Competition::ROLE_PRIMARY)
            ->orderBy('tier')
            ->get();

        return view('select-team', [
            'competitions' => $competitions,
        ]);
    }
}
