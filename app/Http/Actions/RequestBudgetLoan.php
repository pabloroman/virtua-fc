<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\BudgetLoanService;
use Illuminate\Http\Request;

class RequestBudgetLoan
{
    public function __construct(
        private readonly BudgetLoanService $loanService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amountInCents = (int) round($validated['amount'] * 100);

        try {
            $loan = $this->loanService->requestLoan($game, $amountInCents);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.finances', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.finances', $gameId)
            ->with('success', __('messages.budget_loan_approved', [
                'amount' => $loan->formatted_amount,
            ]));
    }
}
