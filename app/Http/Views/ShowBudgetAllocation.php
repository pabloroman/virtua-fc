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

        // Get current tiers (0-4 for each area), default to Tier 1
        $tiers = $investment ? [
            'youth_academy' => $investment->youth_academy_tier,
            'medical' => $investment->medical_tier,
            'scouting' => $investment->scouting_tier,
            'facilities' => $investment->facilities_tier,
        ] : [
            'youth_academy' => 1,
            'medical' => 1,
            'scouting' => 1,
            'facilities' => 1,
        ];

        return view('budget-allocation', [
            'game' => $game,
            'finances' => $finances,
            'investment' => $investment,
            'availableSurplus' => $availableSurplus,
            'tiers' => $tiers,
            'tierThresholds' => GameInvestment::TIER_THRESHOLDS,
            'isLocked' => false,
        ]);
    }
}
