<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\YouthAcademyService;
use App\Models\Game;

/**
 * Generates youth academy prospects at the start of the new season.
 * Runs after budget projection so academy tier is set.
 */
class YouthAcademyProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
    ) {}

    public function priority(): int
    {
        return 55; // After budget projection (50)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Refresh to get latest investment after budget projection
        $game->refresh();

        // Generate youth prospects based on academy tier
        $prospects = $this->youthAcademyService->generateProspects($game);

        if ($prospects->isNotEmpty()) {
            // Store prospect info in metadata for season start display
            $data->setMetadata('youth_prospects', $prospects->map(function ($prospect) {
                return [
                    'id' => $prospect->id,
                    'name' => $prospect->player->name,
                    'position' => $prospect->position,
                    'age' => $prospect->player->age,
                    'potential' => $prospect->potential,
                    'technical' => $prospect->game_technical_ability,
                    'physical' => $prospect->game_physical_ability,
                ];
            })->toArray());
        }

        return $data;
    }
}
