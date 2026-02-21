<?php

namespace App\Modules\Season\Services;

use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\PrimaryCompetitionInitProcessor;
use App\Modules\Season\Processors\SeasonDataCleanupProcessor;
use App\Modules\Season\Processors\SecondaryCompetitionInitProcessor;
use App\Models\Game;

/**
 * Runs the shared "setup" processors used by both initial game creation
 * (SetupNewGame) and season transitions (SeasonEndPipeline).
 *
 * Processors execute in priority order:
 *   SeasonDataCleanupProcessor (10) → PrimaryCompetitionInitProcessor (30) →
 *   BudgetProjectionProcessor (50) → SecondaryCompetitionInitProcessor (106)
 */
class SeasonSetupRunner
{
    public function __construct(
        private readonly SeasonDataCleanupProcessor $cleanupProcessor,
        private readonly PrimaryCompetitionInitProcessor $primaryProcessor,
        private readonly BudgetProjectionProcessor $budgetProcessor,
        private readonly SecondaryCompetitionInitProcessor $secondaryProcessor,
    ) {}

    public function run(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $data = $this->cleanupProcessor->process($game, $data);
        $data = $this->primaryProcessor->process($game, $data);
        $data = $this->budgetProcessor->process($game, $data);
        $data = $this->secondaryProcessor->process($game, $data);

        return $data;
    }
}
