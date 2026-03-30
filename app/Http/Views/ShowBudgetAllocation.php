<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameInvestment;
use App\Modules\Finance\Services\BudgetAllocationService;

class ShowBudgetAllocation
{
    public function __construct(
        private readonly BudgetAllocationService $budgetService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $budgetData = $this->budgetService->prepareBudgetData($game);

        return view('budget-allocation', [
            ...$budgetData,
            'game' => $game,
            'tierThresholds' => GameInvestment::TIER_THRESHOLDS,
            'isLocked' => false,
        ]);
    }
}
