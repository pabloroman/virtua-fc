<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\InfrastructureUpgradeService;
use Illuminate\Http\Request;

class UpgradeInfrastructure
{
    public function __construct(
        private InfrastructureUpgradeService $upgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $validated = $request->validate([
            'area' => 'required|string|in:youth_academy,medical,scouting,facilities',
            'target_tier' => 'required|integer|between:1,4',
        ]);

        try {
            $this->upgradeService->upgrade($game, $validated['area'], (int) $validated['target_tier']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.finances', $gameId)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('game.finances', $gameId)
            ->with('success', __('messages.infrastructure_upgraded', [
                'area' => __("finances.{$validated['area']}"),
                'tier' => $validated['target_tier'],
            ]));
    }
}
