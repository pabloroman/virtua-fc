<?php

namespace App\Http\Actions;

use App\Models\Game;
use Illuminate\Http\Request;

class LookupGame
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'game_id' => ['required', 'uuid'],
        ]);

        $game = Game::with(['user:id,name,email', 'team:id,name'])
            ->find($request->input('game_id'));

        if (! $game) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'game_id' => $game->id,
            'game_mode' => $game->game_mode,
            'season' => $game->season,
            'current_date' => $game->current_date?->format('Y-m-d'),
            'user_name' => $game->user->name,
            'user_email' => $game->user->email,
            'team_name' => $game->team?->name,
            'setup_completed' => $game->setup_completed_at !== null,
        ]);
    }
}
