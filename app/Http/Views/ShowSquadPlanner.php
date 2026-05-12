<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Squad\Services\NextSeasonProjectionService;
use App\Modules\Squad\Services\PlayerSquadRoleClassifier;
use App\Modules\Squad\Services\SquadActionRecommender;
use App\Modules\Squad\Services\SquadAdvisorService;

class ShowSquadPlanner
{
    public function __construct(
        private readonly NextSeasonProjectionService $projectionService,
        private readonly PlayerSquadRoleClassifier $roleClassifier,
        private readonly SquadActionRecommender $actionRecommender,
        private readonly FormationRecommender $formationRecommender,
        private readonly SquadAdvisorService $advisorService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Planner is a career-mode-only surface: contracts, retirements, and
        // pre-contracts (the signals it surfaces) are only meaningful there.
        abort_unless($game->isCareerMode(), 404);

        // The planner is always next-season-focused; the horizon toggle was
        // removed so users land directly on the projection that matters for
        // transfer/development decisions.
        $projection = $this->projectionService->build($game, NextSeasonProjectionService::HORIZON_NEXT);

        // Auto-pick the best-fit formation for the projected pool — used
        // internally by the role classifier (FIRST_TEAM) and the squad
        // advisor (depth gaps). The user-facing Tactics Hub selector was
        // removed; surfacing the chosen shape will come back in a future
        // iteration once the UX is settled.
        $formation = $this->pickFormation($projection);

        $projection = $this->roleClassifier->classify($projection, $formation);
        $projection = $this->actionRecommender->recommend($projection, $game);

        $advisories = $this->advisorService->build($projection, $formation, $game);

        return view('squad-planner', [
            'game' => $game,
            'projection' => $projection,
            'advisories' => $advisories,
        ]);
    }

    private function pickFormation(array $projection): Formation
    {
        $available = $this->projectionService->availablePool($projection);

        if ($available->isEmpty()) {
            return Formation::F_4_3_3;
        }

        return $this->formationRecommender->getBestFormation($available);
    }
}
