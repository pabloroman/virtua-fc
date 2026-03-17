<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Transfer\Services\ExploreService;
use Illuminate\Http\Request;

class ExploreFreeAgents
{
    public function __construct(
        private readonly ExploreService $exploreService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $positionFilter = $request->query('position', 'all');
        $players = $this->exploreService->getFreeAgents($game, $positionFilter);

        return view('partials.explore-free-agents', [
            'players' => $players,
            'game' => $game,
            'isTransferWindow' => $game->isTransferWindowOpen(),
        ]);
    }
}
