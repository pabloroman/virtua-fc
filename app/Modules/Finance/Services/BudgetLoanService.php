<?php

namespace App\Modules\Finance\Services;

use App\Models\BudgetLoan;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Modules\Notification\Services\NotificationService;

class BudgetLoanService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}
    /**
     * Calculate the maximum loan amount for a game (10% of projected total revenue).
     */
    public function maxLoanAmount(Game $game): int
    {
        $finances = $game->currentFinances;

        if (!$finances || $finances->projected_total_revenue <= 0) {
            return 0;
        }

        $percentage = config('finances.loan.max_percentage', 0.10);
        $raw = (int) ($finances->projected_total_revenue * $percentage);

        // Round down to nearest €100K (10_000_000 cents) for a clean number
        return (int) (floor($raw / 10_000_000) * 10_000_000);
    }

    /**
     * Check if the user can request a budget loan.
     */
    public function canRequestLoan(Game $game): bool
    {
        // Must have an active investment (budget allocated)
        if (!$game->currentInvestment) {
            return false;
        }

        // Transfer window must be open
        if (!$game->isTransferWindowOpen()) {
            return false;
        }

        // No active loan already
        if ($this->activeLoan($game) !== null) {
            return false;
        }

        // Must have financial projections
        if (!$game->currentFinances) {
            return false;
        }

        return true;
    }

    /**
     * Get the active budget loan for a game, if any.
     */
    public function activeLoan(Game $game): ?BudgetLoan
    {
        return BudgetLoan::where('game_id', $game->id)
            ->where('status', BudgetLoan::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Request a budget loan. Adds funds to the transfer budget.
     *
     * @throws \InvalidArgumentException
     */
    public function requestLoan(Game $game, int $amountInCents): BudgetLoan
    {
        if (!$this->canRequestLoan($game)) {
            throw new \InvalidArgumentException('messages.loan_not_available');
        }

        $minimum = config('finances.loan.minimum', 50_000_000);
        if ($amountInCents < $minimum) {
            throw new \InvalidArgumentException('messages.loan_below_minimum');
        }

        $maxAmount = $this->maxLoanAmount($game);
        if ($amountInCents > $maxAmount) {
            throw new \InvalidArgumentException('messages.loan_exceeds_maximum');
        }

        $interestRate = config('finances.loan.interest_rate', 1500); // basis points
        $repaymentAmount = (int) ($amountInCents * (1 + $interestRate / 10000));

        $loan = BudgetLoan::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'amount' => $amountInCents,
            'interest_rate' => $interestRate,
            'repayment_amount' => $repaymentAmount,
            'status' => BudgetLoan::STATUS_ACTIVE,
        ]);

        // Add loan amount to transfer budget
        $investment = $game->currentInvestment;
        $investment->increment('transfer_budget', $amountInCents);

        // Record financial transaction
        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_BUDGET_LOAN,
            amount: $loan->amount,
            description: __('finances.tx_budget_loan_received', ['amount' => $loan->formatted_amount]),
            transactionDate: $game->current_date->toDateString(),
        );

        // Notify the user
        $this->notificationService->notifyBudgetLoanTaken(
            $game,
            $loan->formatted_amount,
            $loan->formatted_repayment_amount,
        );

        return $loan;
    }

    /**
     * Repay an active loan during season settlement.
     * Returns the repayment amount that was deducted.
     */
    public function repayLoan(BudgetLoan $loan): int
    {
        $loan->update(['status' => BudgetLoan::STATUS_REPAID]);

        return $loan->repayment_amount;
    }
}
