<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Modules\Finance\Services\StadiumUpgradeService;
use Illuminate\Http\Request;

class CommitStandExpansion
{
    public function __construct(
        private readonly StadiumUpgradeService $stadiumUpgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $validated = $request->validate([
            'seats' => 'required|integer|min:1',
            'financing' => 'required|in:'.GameStadiumProject::FINANCING_CASH.','.GameStadiumProject::FINANCING_LOAN,
        ]);

        try {
            $this->stadiumUpgradeService->commitStandExpansion(
                $game,
                (int) $validated['seats'],
                $validated['financing'],
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.club.stadium', $gameId)
            ->with('success', __('messages.stadium_stand_expansion_committed', [
                'seats' => number_format((int) $validated['seats'], 0, ',', '.'),
            ]));
    }
}
