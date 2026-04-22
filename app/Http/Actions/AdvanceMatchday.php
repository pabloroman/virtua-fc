<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchdayAdvanceCoordinator $coordinator,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // If the user is in fast mode, the normal advance path is disabled —
        // they must explicitly exit fast mode (or use the fast-mode advance
        // route) to play matches.
        if ($game->isFastMode()) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        $this->coordinator->dispatchAsync($gameId);

        // ShowGame polls matchday_advance_result and routes onward once the
        // job finishes. A failed claim means another request already holds
        // the flag, which lands on the same loading screen anyway.
        return redirect()->route('show-game', $gameId);
    }
}
