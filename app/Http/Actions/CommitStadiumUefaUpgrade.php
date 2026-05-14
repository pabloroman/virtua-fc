<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Modules\Finance\Services\StadiumUpgradeService;
use Illuminate\Http\Request;

class CommitStadiumUefaUpgrade
{
    public function __construct(
        private readonly StadiumUpgradeService $stadiumUpgradeService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $validated = $request->validate([
            'financing' => 'required|in:'.GameStadiumProject::FINANCING_CASH.','.GameStadiumProject::FINANCING_LOAN,
        ]);

        try {
            $project = $this->stadiumUpgradeService->commitUefaUpgrade(
                $game,
                $validated['financing'],
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('game.club.stadium', $gameId)
                ->with('error', __($e->getMessage()));
        }

        return redirect()->route('game.club.stadium', $gameId)
            ->with('success', __('messages.stadium_uefa_upgrade_committed', [
                'level' => (int) $project->target_capacity,
            ]));
    }
}
