<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\BudgetProjectionService;
use App\Models\Game;

/**
 * Generates budget projections for the new season.
 * Runs after all other processors so we have the new squad and standings.
 */
class BudgetProjectionProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
    ) {}

    public function priority(): int
    {
        return 50; // After everything else, right before pre-season starts
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Update the game's season to the new season before generating projections
        $game->update(['season' => $data->newSeason]);

        // Generate projections for the new season
        $finances = $this->projectionService->generateProjections($game);

        // Store projections in metadata for pre-season display
        $data->setMetadata('new_season_projections', [
            'projected_position' => $finances->projected_position,
            'projected_total_revenue' => $finances->projected_total_revenue,
            'projected_wages' => $finances->projected_wages,
            'projected_surplus' => $finances->projected_surplus,
            'carried_debt' => $finances->carried_debt,
            'available_surplus' => $finances->available_surplus,
        ]);

        return $data;
    }
}
