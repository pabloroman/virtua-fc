<?php

namespace App\Http\Views;

use App\Modules\Finance\Services\BudgetLoanService;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Finance\Services\SalaryCapService;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;

class ShowFinances
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
        private readonly BudgetLoanService $loanService,
        private readonly SalaryCapService $salaryCapService,
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

        // Calculate current squad metrics (value + headcount in a single query)
        $squadMetrics = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->selectRaw('COALESCE(SUM(market_value_cents), 0) as squad_value, COUNT(*) as squad_size')
            ->first();
        $squadValue = (int) $squadMetrics->squad_value;
        $squadSize = (int) $squadMetrics->squad_size;

        // Get transactions for the current season (July 1 → June 30)
        $seasonYear = (int) $game->season;
        $seasonStart = "{$seasonYear}-07-01";
        $seasonEnd = ($seasonYear + 1) . '-06-30';

        $transactions = FinancialTransaction::with('relatedPlayer')
            ->where('game_id', $gameId)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->orderByDesc('transaction_date')
            ->limit(20)
            ->get();

        // Transaction totals for summary bar
        $totalIncome = $transactions->where('type', FinancialTransaction::TYPE_INCOME)->sum('amount');
        $totalExpenses = $transactions->where('type', FinancialTransaction::TYPE_EXPENSE)->sum('amount');

        // Salary cap ("Límite de Coste de Plantilla"): the committed wage bill
        // measured against the cap derived from recurring revenue (plus the
        // trailing player-trading allowance — plusvalías).
        $salaryCap = $this->salaryCapService->cap($game);
        $salaryCapBill = $this->salaryCapService->committedWageBill($game);
        $salaryCapRoom = $this->salaryCapService->remainingRoom($game);
        $salaryCapStatus = $this->salaryCapService->status($game);
        // Cap room earned from sustained net player sales (0 for net buyers).
        $tradingAllowanceRoom = $this->salaryCapService->tradingAllowanceRoom($game);

        // The cap as a % of its base (≈70%), surfaced in the help text. The base
        // is recurring revenue plus the trading allowance, so the ratio stays
        // ≈70% rather than drifting above it once plusvalías widen the cap.
        // Derived from the actual cap so it stays correct if the ratio is ever
        // tuned per-reputation rather than the flat config scalar.
        $capBase = $finances->capBase();
        $salaryCapRatioPercent = $capBase > 0
            ? (int) round($salaryCap / $capBase * 100)
            : (int) round(config('finances.wage_cap_ratio', 0.70) * 100);

        // Transfer activity totals for budget flow breakdown (single query)
        $activityTotals = FinancialTransaction::where('game_id', $gameId)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->whereIn('category', [
                FinancialTransaction::CATEGORY_TRANSFER_IN,
                FinancialTransaction::CATEGORY_TRANSFER_OUT,
                FinancialTransaction::CATEGORY_INFRASTRUCTURE,
                FinancialTransaction::CATEGORY_STADIUM,
            ])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN category = ? AND type = ? THEN amount ELSE 0 END), 0) as sales_revenue,
                COALESCE(SUM(CASE WHEN category = ? AND type = ? THEN amount ELSE 0 END), 0) as purchase_spending,
                COALESCE(SUM(CASE WHEN category IN (?, ?) AND type = ? THEN amount ELSE 0 END), 0) as infrastructure_spending
            ", [
                FinancialTransaction::CATEGORY_TRANSFER_IN, FinancialTransaction::TYPE_INCOME,
                FinancialTransaction::CATEGORY_TRANSFER_OUT, FinancialTransaction::TYPE_EXPENSE,
                FinancialTransaction::CATEGORY_INFRASTRUCTURE, FinancialTransaction::CATEGORY_STADIUM,
                FinancialTransaction::TYPE_EXPENSE,
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

        return view('club.finances', [
            'game' => $game,
            'finances' => $finances,
            'investment' => $investment,
            'squadValue' => $squadValue,
            'squadSize' => $squadSize,
            'transactions' => $transactions,
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'salaryCap' => $salaryCap,
            'salaryCapRatioPercent' => $salaryCapRatioPercent,
            'salaryCapBill' => $salaryCapBill,
            'salaryCapRoom' => $salaryCapRoom,
            'salaryCapStatus' => $salaryCapStatus,
            'tradingAllowanceRoom' => $tradingAllowanceRoom,
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
