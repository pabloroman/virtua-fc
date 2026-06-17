<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\BudgetAllocationService;
use App\Modules\Finance\Services\InvestmentStateService;
use Illuminate\Http\Request;

class SaveClubInvestment
{
    public function __construct(
        private BudgetAllocationService $budgetService,
        private InvestmentStateService $stateService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Free two-way re-allocation is only allowed in pre-season. Once the
        // season is live the plan is locked to upgrade-only (the upgrade and
        // stage-downgrade endpoints handle in-season changes).
        if (! $this->stateService->isEditableFreely($game)) {
            return redirect()->route('game.club.investment', $gameId)
                ->with('error', __('messages.investment_locked_no_edit'));
        }

        $validated = $request->validate([
            'youth_academy' => 'required|numeric|min:0',
            'medical' => 'required|numeric|min:0',
            'scouting' => 'required|numeric|min:0',
            'facilities' => 'required|numeric|min:0',
            'transfer_budget' => 'required|numeric|min:0',
        ]);

        try {
            $this->budgetService->allocate($game, $validated);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.club.investment', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.club.investment', $gameId)
            ->with('success', __('messages.investment_saved'));
    }
}
