<?php

namespace App\Http\Views;

use App\Game\Services\BudgetProjectionService;
use App\Models\Game;
use App\Models\GameInvestment;

class ShowBudgetAllocation
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Access relationships after model is loaded (lazy loading works correctly)
        $finances = $game->currentFinances;
        if (!$finances) {
            $finances = $this->projectionService->generateProjections($game);
        }

        $investment = $game->currentInvestment;

        // Calculate available surplus
        $availableSurplus = $finances->available_surplus ?? 0;

        // Get current allocations (or defaults)
        $allocations = $investment ? [
            'youth_academy' => $investment->youth_academy_amount,
            'medical' => $investment->medical_amount,
            'scouting' => $investment->scouting_amount,
            'facilities' => $investment->facilities_amount,
            'transfer_budget' => $investment->transfer_budget,
        ] : $this->getDefaultAllocations($availableSurplus);

        // Calculate tiers for each area
        $tiers = [
            'youth_academy' => GameInvestment::calculateTier('youth_academy', $allocations['youth_academy']),
            'medical' => GameInvestment::calculateTier('medical', $allocations['medical']),
            'scouting' => GameInvestment::calculateTier('scouting', $allocations['scouting']),
            'facilities' => GameInvestment::calculateTier('facilities', $allocations['facilities']),
        ];

        return view('budget-allocation', [
            'game' => $game,
            'finances' => $finances,
            'investment' => $investment,
            'availableSurplus' => $availableSurplus,
            'allocations' => $allocations,
            'tiers' => $tiers,
            'tierThresholds' => GameInvestment::TIER_THRESHOLDS,
            'minimumInvestment' => GameInvestment::MINIMUM_TOTAL_INVESTMENT,
            'isLocked' => !$game->isInPreseason(), // Can adjust during preseason, locked once season starts
        ]);
    }

    /**
     * Get default allocations - start at zero, let user allocate.
     */
    private function getDefaultAllocations(int $availableSurplus): array
    {
        return [
            'youth_academy' => 0,
            'medical' => 0,
            'scouting' => 0,
            'facilities' => 0,
            'transfer_budget' => 0,
        ];
    }
}
