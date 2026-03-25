<?php

namespace App\Http\Actions;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Modules\Finance\Services\BudgetLoanService;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\Request;

class RequestBudgetLoan
{
    public function __construct(
        private readonly BudgetLoanService $loanService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        // Convert euros to cents
        $amountInCents = (int) round($validated['amount'] * 100);

        try {
            $loan = $this->loanService->requestLoan($game, $amountInCents);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.finances', $gameId)
                ->with('error', __($e->getMessage()));
        }

        // Record the loan as a financial transaction
        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_BUDGET_LOAN,
            amount: $loan->amount,
            description: __('finances.tx_budget_loan_received', ['amount' => $loan->formatted_amount]),
            transactionDate: $game->current_date->toDateString(),
        );

        // Send notification
        $this->notificationService->notifyBudgetLoanTaken(
            $game,
            $loan->formatted_amount,
            $loan->formatted_repayment_amount,
        );

        return redirect()->route('game.finances', $gameId)
            ->with('success', __('messages.budget_loan_approved', [
                'amount' => $loan->formatted_amount,
            ]));
    }
}
