<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Transfer\Services\ExploreService;
use Illuminate\Http\Request;

class ExploreEuropeTeams
{
    public function __construct(
        private readonly ExploreService $exploreService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        return response()->json(
            $this->exploreService->getEuropeanTeamsGroupedByCountry($gameId)
        );
    }
}
