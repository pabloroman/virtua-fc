<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\FinancialService;
use App\Models\Game;

/**
 * Calculates season-end financials: revenue, expenses, profit/loss.
 * Runs before standings are reset so we can use final league position.
 */
class FinancialProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly FinancialService $financialService,
    ) {}

    public function priority(): int
    {
        return 15; // After archive (5) and development (10), before stats reset (20)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Calculate end-of-season financials
        $finances = $this->financialService->calculateSeasonEnd($game);

        // Store in metadata for the season-end screen
        $data->setMetadata('finances', [
            'tv_revenue' => $finances->tv_revenue,
            'performance_bonus' => $finances->performance_bonus,
            'cup_bonus' => $finances->cup_bonus,
            'total_revenue' => $finances->total_revenue,
            'wage_expense' => $finances->wage_expense,
            'transfer_expense' => $finances->transfer_expense,
            'total_expense' => $finances->total_expense,
            'season_profit_loss' => $finances->season_profit_loss,
            'balance' => $finances->balance,
        ]);

        return $data;
    }
}
