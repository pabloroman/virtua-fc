<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Squad\Services\NextSeasonProjectionService;
use App\Modules\Squad\Services\PlayerSquadRoleClassifier;
use App\Modules\Squad\Services\SquadActionRecommender;

class ShowSquadPlanner
{
    public function __construct(
        private readonly NextSeasonProjectionService $projectionService,
        private readonly PlayerSquadRoleClassifier $roleClassifier,
        private readonly SquadActionRecommender $actionRecommender,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Planner is a career-mode-only surface: contracts, retirements, and
        // pre-contracts (the signals it surfaces) are only meaningful there.
        abort_unless($game->isCareerMode(), 404);

        $projection = $this->projectionService->build($game);
        $projection = $this->roleClassifier->classify($projection);
        $projection = $this->actionRecommender->recommend($projection, $game);

        return view('squad-planner', [
            'game' => $game,
            'projection' => $projection,
        ]);
    }
}
