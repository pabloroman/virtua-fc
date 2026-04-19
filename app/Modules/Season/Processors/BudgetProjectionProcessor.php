<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Season\Services\SeasonGoalService;
use App\Models\Competition;
use App\Models\Game;

/**
 * Generates budget projections for the new season.
 *
 * Runs after ContinentalAndCupInitProcessor (106) so that cup round-1 draws
 * and Swiss-format fixtures are in place — BudgetProjectionService walks
 * scheduled home fixtures for the demand-curve projection, and it needs the
 * full schedule (league + Swiss + round-1 cup) to be visible.
 */
class BudgetProjectionProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    public function priority(): int
    {
        return 107; // After ContinentalAndCupInit (106), before PreSeasonFixture (108)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Determine season goal based on team reputation and competition
        $competition = Competition::find($game->competition_id);
        $promotedTeams = $data->getMetadata('promotedTeams', []);
        $recentlyPromoted = collect($promotedTeams)->contains('teamId', $game->team_id);
        $seasonGoal = $this->seasonGoalService->determineGoalForTeam($game->team, $competition, $game, $recentlyPromoted);

        $game->update(['season_goal' => $seasonGoal]);

        // Generate projections for the new season
        $finances = $this->projectionService->generateProjections($game);

        // Store projections in metadata for season display
        $data->setMetadata('new_season_projections', [
            'projected_position' => $finances->projected_position,
            'projected_total_revenue' => $finances->projected_total_revenue,
            'projected_wages' => $finances->projected_wages,
            'projected_surplus' => $finances->projected_surplus,
            'carried_debt' => $finances->carried_debt,
            'carried_surplus' => $finances->carried_surplus,
            'available_surplus' => $finances->available_surplus,
            'season_goal' => $seasonGoal,
        ]);

        return $data;
    }
}
