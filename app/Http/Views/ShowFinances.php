<?php

namespace App\Http\Views;

use App\Modules\Finance\Services\BudgetProjectionService;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;

class ShowFinances
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Access relationships after model is loaded (lazy loading works correctly)
        $finances = $game->currentFinances;
        $investment = $game->currentInvestment;

        // Generate projections if not exists
        if (!$finances) {
            $finances = $this->projectionService->generateProjections($game);
        }

        // Calculate current squad metrics
        $squadValue = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('market_value_cents');

        $wageBill = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');

        // Get recent transactions
        $transactions = FinancialTransaction::with('relatedPlayer.player')
            ->where('game_id', $gameId)
            ->orderByDesc('transaction_date')
            ->limit(20)
            ->get();

        // Transaction totals for summary bar
        $totalIncome = $transactions->where('type', FinancialTransaction::TYPE_INCOME)->sum('amount');
        $totalExpenses = $transactions->where('type', FinancialTransaction::TYPE_EXPENSE)->sum('amount');

        // Wage-to-revenue ratio
        $wageRevenueRatio = $finances->projected_total_revenue > 0
            ? round(($finances->projected_wages / $finances->projected_total_revenue) * 100)
            : 0;

        return view('finances', [
            'game' => $game,
            'finances' => $finances,
            'investment' => $investment,
            'squadValue' => $squadValue,
            'wageBill' => $wageBill,
            'transactions' => $transactions,
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'wageRevenueRatio' => $wageRevenueRatio,
        ]);
    }
}
