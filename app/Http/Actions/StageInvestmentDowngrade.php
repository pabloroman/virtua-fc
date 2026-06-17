<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\InvestmentStateService;
use Illuminate\Http\Request;

class StageInvestmentDowngrade
{
    public function __construct(
        private InvestmentStateService $stateService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $validated = $request->validate([
            'area' => 'required|string|in:youth_academy,medical,scouting',
            'target_tier' => 'nullable|integer|between:0,4',
        ]);

        // An empty target tier cancels a previously staged downgrade.
        $cleared = $request->input('target_tier') === null || $request->input('target_tier') === '';

        try {
            if ($cleared) {
                $this->stateService->clearStagedDowngrade($game, $validated['area']);
            } else {
                $this->stateService->stageDowngrade($game, $validated['area'], (int) $validated['target_tier']);
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.club.investment', $gameId)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('game.club.investment', $gameId)
            ->with('success', __($cleared ? 'messages.investment_downgrade_cleared' : 'messages.investment_downgrade_staged'));
    }
}
