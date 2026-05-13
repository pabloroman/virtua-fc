<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Finance\Services\StadiumUpgradeService;
use Illuminate\Http\Request;

class CommitSupplementaryStands
{
    public function __construct(
        private readonly StadiumUpgradeService $stadiumUpgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $validated = $request->validate([
            'seats' => 'required|integer|min:1',
        ]);

        try {
            $this->stadiumUpgradeService->commitSupplementary($game, (int) $validated['seats']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.club.stadium', $gameId)
            ->with('success', __('messages.stadium_supplementary_committed', [
                'seats' => number_format((int) $validated['seats'], 0, ',', '.'),
            ]));
    }
}
