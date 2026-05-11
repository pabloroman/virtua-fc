<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Squad\Services\NextSeasonProjectionService;
use App\Modules\Squad\Services\PlayerSquadRoleClassifier;
use App\Modules\Squad\Services\SquadActionRecommender;
use Illuminate\Http\Request;

class ShowSquadPlanner
{
    public function __construct(
        private readonly NextSeasonProjectionService $projectionService,
        private readonly PlayerSquadRoleClassifier $roleClassifier,
        private readonly SquadActionRecommender $actionRecommender,
        private readonly FormationRecommender $formationRecommender,
    ) {}

    public function __invoke(string $gameId, Request $request)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Planner is a career-mode-only surface: contracts, retirements, and
        // pre-contracts (the signals it surfaces) are only meaningful there.
        abort_unless($game->isCareerMode(), 404);

        $projection = $this->projectionService->build($game);

        $formation = $this->resolveFormation($request, $projection);

        $projection = $this->roleClassifier->classify($projection, $formation);
        $projection = $this->actionRecommender->recommend($projection, $game);

        $formationFit = $this->projectionService->buildFormationFit($projection, $formation);

        return view('squad-planner', [
            'game' => $game,
            'projection' => $projection,
            'formation' => $formation,
            'formationFit' => $formationFit,
        ]);
    }

    /**
     * Resolve the formation to evaluate the squad against. Honors a `?formation`
     * query param when it matches a known Formation case, otherwise falls back
     * to the recommender's best-fit pick against the projected squad.
     */
    private function resolveFormation(Request $request, array $projection): Formation
    {
        $requested = $request->query('formation');
        if (is_string($requested)) {
            $match = Formation::tryFrom($requested);
            if ($match !== null) {
                return $match;
            }
        }

        $available = $this->projectionService->availablePool($projection);

        if ($available->isEmpty()) {
            return Formation::F_4_3_3;
        }

        return $this->formationRecommender->getBestFormation($available);
    }
}
