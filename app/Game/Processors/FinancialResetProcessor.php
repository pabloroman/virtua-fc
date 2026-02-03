<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\FinancialService;
use App\Models\Game;

/**
 * Prepares finances for the new season: resets budgets while carrying over balance.
 * Runs after standings reset since we need the new season's squad value.
 */
class FinancialResetProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly FinancialService $financialService,
    ) {}

    public function priority(): int
    {
        return 45; // After standings reset (40)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Prepare finances for the new season
        $finances = $this->financialService->prepareNewSeason($game);

        // Store new season budgets in metadata
        $data->setMetadata('new_season_finances', [
            'wage_budget' => $finances->wage_budget,
            'transfer_budget' => $finances->transfer_budget,
            'balance' => $finances->balance,
        ]);

        return $data;
    }
}
