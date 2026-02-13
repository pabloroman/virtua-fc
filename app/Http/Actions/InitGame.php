<?php

namespace App\Http\Actions;

use App\Game\Commands\CreateGame;
use App\Game\Game;
use App\Models\Game as GameModel;
use Illuminate\Http\Request;

class InitGame
{
    public function __invoke(Request $request)
    {
        $gameCount = GameModel::where('user_id', $request->user()->id)->count();
        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:25'],
            'team_id' => ['required', 'uuid'],
        ]);

        $command = new CreateGame(
            userId: (string) $request->user()->id,
            playerName: $request->get('name'),
            teamId: $request->get('team_id'),
        );

        $game = Game::create($command);

        return redirect()->route('game.onboarding', $game->uuid());
    }
}
