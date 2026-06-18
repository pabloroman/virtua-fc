<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Season\Services\PreseasonOpponentService;

class ShowPreseasonSetup
{
    public function __construct(
        private readonly PreseasonOpponentService $opponentService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Still building the season — let show-game render the loading screen.
        if (! $game->isSetupComplete() || $game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Selection already done (or not applicable) — back to the dashboard.
        if (! $game->needsPreseasonOpponentSelection()) {
            return redirect()->route('show-game', $gameId);
        }

        $candidates = $this->opponentService->candidatePool($game);
        $slots = $this->opponentService->fixtureSlots($game);

        return view('preseason-setup', [
            'game' => $game,
            'candidates' => $candidates,
            'slots' => $slots,
        ]);
    }
}
