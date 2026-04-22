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

        // ShowGame renders the game-loading-matchday view while the job runs,
        // then consumes matchday_advance_result and redirects to the live-match
        // screen (or season-end / pending action) once the job completes. If
        // the claim failed because another request already holds the flag,
        // the same loading screen is what the user wants to see anyway.
        return redirect()->route('show-game', $gameId);
    }
}
