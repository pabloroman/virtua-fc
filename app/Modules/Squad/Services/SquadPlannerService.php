<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Squad\DTOs\Advisory;
use App\Modules\Squad\DTOs\SquadContext;

/**
 * Single entry point that produces the full Squad Planner payload.
 *
 * Orchestrates the four-step pipeline:
 *   1. Next-season projection (staying / outgoing / incoming).
 *   2. Auto-picked formation for the projected pool.
 *   3. Per-player role classification + auto-blurb.
 *   4. Per-player action recommendation and squad-level advisories.
 *
 * Keeps `ShowSquadPlanner` to a single service call.
 */
class SquadPlannerService
{
    public function __construct(
        private readonly NextSeasonProjectionService $projectionService,
        private readonly PlayerSquadRoleClassifier $roleClassifier,
        private readonly SquadActionRecommender $actionRecommender,
        private readonly FormationRecommender $formationRecommender,
        private readonly SquadAdvisorService $advisorService,
    ) {}

    /**
     * @return array{
     *     projection: array,
     *     advisories: array<int, Advisory>,
     *     formation: Formation,
     * }
     */
    public function build(Game $game): array
    {
        $projection = $this->projectionService->build($game);

        // Auto-pick the best-fit formation for the projected pool — used
        // internally by the role classifier (FIRST_TEAM) and the squad
        // advisor (depth gaps). The user-facing Tactics Hub selector was
        // removed; surfacing the chosen shape will come back in a future
        // iteration once the UX is settled.
        $formation = $this->pickFormation($projection);

        $projection = $this->roleClassifier->classify($projection, $formation);

        // Shared per-squad reference stats — keeps recommender + advisor
        // anchored to the actual roster's capacity (worst projected starter
        // per group, group sizes, formation needs) instead of absolute
        // overall thresholds that misfire on extremely strong or weak squads.
        $context = SquadContext::fromProjection($projection, $formation);

        $projection = $this->actionRecommender->recommend($projection, $game, $context);

        $advisories = $this->advisorService->build($projection, $formation, $game, $context);

        return [
            'projection' => $projection,
            'advisories' => $advisories,
            'formation' => $formation,
        ];
    }

    private function pickFormation(array $projection): Formation
    {
        $available = NextSeasonProjectionService::availablePool($projection);

        if ($available->isEmpty()) {
            return Formation::F_4_3_3;
        }

        return $this->formationRecommender->getBestFormation($available);
    }
}
