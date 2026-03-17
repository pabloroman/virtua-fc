<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Transfer\Services\ExploreService;
use Illuminate\Http\Request;

class ExploreTeams
{
    public function __construct(
        private readonly ExploreService $exploreService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $competitionId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        return response()->json(
            $this->exploreService->getTeamsForCompetition($gameId, $competitionId)
        );
    }
}
