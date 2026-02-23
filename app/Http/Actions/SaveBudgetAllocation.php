<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\BudgetAllocationService;
use Illuminate\Http\Request;

class SaveBudgetAllocation
{
    public function __construct(
        private BudgetAllocationService $budgetService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $finances = $game->currentFinances;
        if (!$finances) {
            return redirect()->route('game.budget', $gameId)
                ->with('error', __('messages.budget_no_projections'));
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
            return redirect()->route('game.budget', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.finances', $gameId)
            ->with('success', __('messages.budget_saved'));
    }
}
