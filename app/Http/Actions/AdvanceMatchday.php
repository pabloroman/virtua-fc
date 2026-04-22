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

        // Run inline: with sibling matches on the AIMatchResolver fast path,
        // a full matchday advance completes sub-second, so the queue hop +
        // 2s polling cycle cost more than the work itself. A client-side
        // overlay in game-header shows the branded loading screen while this
        // request is in flight. runSync returning null (another request
        // already holds the flag) falls through to ShowGame, which renders
        // game-loading-matchday and polls — the existing safety net.
        $this->coordinator->runSync($gameId);

        return redirect()->route('show-game', $gameId);
    }
}
