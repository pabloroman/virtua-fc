<?php

namespace App\Http\Actions;

use App\Models\Game;

class CompleteWelcome
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (!$game->needsWelcome()) {
            return redirect()->route('game.onboarding', $gameId);
        }

        $game->completeWelcome();

        return redirect()->route('game.onboarding', $gameId);
    }
}
