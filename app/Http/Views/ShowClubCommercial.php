<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Stadium\Services\NamingRightsService;

/**
 * Commercial hub: where the manager proactively seeks naming-rights
 * sponsors. Reads the commercial panel from NamingRightsService — the active
 * deal, the pending offer board, and the proactive-search state.
 */
class ShowClubCommercial
{
    public function __construct(
        private readonly NamingRightsService $namingRightsService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $panel = $this->namingRightsService->buildCommercialPanel($game)['namingRights'];

        return view('club.commercial', [
            'game' => $game,
            'namingRights' => $panel,
        ]);
    }
}
