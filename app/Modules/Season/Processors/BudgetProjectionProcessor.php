<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Season\Services\SeasonGoalService;
use App\Models\Competition;
use App\Models\Game;

/**
 * Generates budget projections for the new season.
 * Runs after all other processors so we have the new squad and standings.
 */
class BudgetProjectionProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    public function priority(): int
    {
        return 50; // After everything else, right before pre-season starts
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Determine season goal based on team reputation and competition
        $competition = Competition::find($game->competition_id);
        $seasonGoal = $this->seasonGoalService->determineGoalForTeam($game->team, $competition);

        // Update season goal (and advance season year on season transitions)
        $updates = ['season_goal' => $seasonGoal];
        if (!$data->isInitialSeason) {
            $updates['season'] = $data->newSeason;
        }
        $game->update($updates);

        // Generate projections for the new season
        $finances = $this->projectionService->generateProjections($game);

        // Store projections in metadata for season display
        $data->setMetadata('new_season_projections', [
            'projected_position' => $finances->projected_position,
            'projected_total_revenue' => $finances->projected_total_revenue,
            'projected_wages' => $finances->projected_wages,
            'projected_surplus' => $finances->projected_surplus,
            'carried_debt' => $finances->carried_debt,
            'available_surplus' => $finances->available_surplus,
            'season_goal' => $seasonGoal,
        ]);

        return $data;
    }
}
