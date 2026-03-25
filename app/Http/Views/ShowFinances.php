<?php

namespace App\Http\Views;

use App\Modules\Finance\Services\BudgetLoanService;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\TransferOffer;

class ShowFinances
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
        private readonly BudgetLoanService $loanService,
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

        // Get transactions for the current season (July 1 → June 30)
        $seasonYear = (int) $game->season;
        $seasonStart = "{$seasonYear}-07-01";
        $seasonEnd = ($seasonYear + 1) . '-06-30';

        $transactions = FinancialTransaction::with('relatedPlayer.player')
            ->where('game_id', $gameId)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
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

        // Available transfer budget for infrastructure upgrades
        $availableBudget = $investment
            ? $investment->transfer_budget - TransferOffer::committedBudget($game->id)
            : 0;

        // Transfer activity totals for budget flow breakdown (single query)
        $activityTotals = FinancialTransaction::where('game_id', $gameId)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->whereIn('category', [
                FinancialTransaction::CATEGORY_TRANSFER_IN,
                FinancialTransaction::CATEGORY_TRANSFER_OUT,
                FinancialTransaction::CATEGORY_INFRASTRUCTURE,
            ])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN category = ? AND type = ? THEN amount ELSE 0 END), 0) as sales_revenue,
                COALESCE(SUM(CASE WHEN category = ? AND type = ? THEN amount ELSE 0 END), 0) as purchase_spending,
                COALESCE(SUM(CASE WHEN category = ? AND type = ? THEN amount ELSE 0 END), 0) as infrastructure_spending
            ", [
                FinancialTransaction::CATEGORY_TRANSFER_IN, FinancialTransaction::TYPE_INCOME,
                FinancialTransaction::CATEGORY_TRANSFER_OUT, FinancialTransaction::TYPE_EXPENSE,
                FinancialTransaction::CATEGORY_INFRASTRUCTURE, FinancialTransaction::TYPE_EXPENSE,
            ])
            ->first();

        $salesRevenue = (int) $activityTotals->sales_revenue;
        $purchaseSpending = (int) $activityTotals->purchase_spending;
        $infrastructureSpending = (int) $activityTotals->infrastructure_spending;

        // Budget loan data
        $activeLoan = $this->loanService->activeLoan($game);
        $loanAmount = $activeLoan?->amount ?? 0;

        // Initial transfer budget = current budget - sales + purchases + infrastructure - loan
        $initialTransferBudget = $investment
            ? $investment->transfer_budget - $salesRevenue + $purchaseSpending + $infrastructureSpending - $loanAmount
            : 0;

        $hasTransferActivity = $salesRevenue > 0 || $purchaseSpending > 0 || $infrastructureSpending > 0 || $loanAmount > 0;
        $canRequestLoan = $this->loanService->canRequestLoan($game);
        $maxLoanAmount = $this->loanService->maxLoanAmount($game);

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
            'tierThresholds' => GameInvestment::TIER_THRESHOLDS,
            'availableBudget' => $availableBudget,
            'initialTransferBudget' => $initialTransferBudget,
            'salesRevenue' => $salesRevenue,
            'purchaseSpending' => $purchaseSpending,
            'infrastructureSpending' => $infrastructureSpending,
            'hasTransferActivity' => $hasTransferActivity,
            'activeLoan' => $activeLoan,
            'canRequestLoan' => $canRequestLoan,
            'maxLoanAmount' => $maxLoanAmount,
        ]);
    }
}
