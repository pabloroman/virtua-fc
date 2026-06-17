<?php

namespace App\Modules\Season\Processors;

use App\Modules\Finance\Services\BudgetAllocationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;

/**
 * Auto-applies the season's default investment allocation so a GameInvestment
 * row (and a transfer budget) exists the moment setup finishes — for both the
 * initial season and every transition. This is what lets the new-season screen
 * stop being a blocking budget gate: the board sets a sane starting plan and the
 * manager refines it later, reversibly, on the Club investment page.
 *
 * Runs after BudgetProjectionProcessor (107), which generates the finances the
 * default sizing reads, and before NewSeasonResetProcessor (110). Unlike the
 * reset processor it runs for the initial season too — every season needs a
 * starting allocation.
 */
class DefaultInvestmentProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly BudgetAllocationService $budgetService,
    ) {}

    public function priority(): int
    {
        return 109;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->budgetService->applyDefaultAllocation($game);

        return $data;
    }
}
