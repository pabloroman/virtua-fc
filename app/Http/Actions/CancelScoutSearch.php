<?php

namespace App\Http\Actions;

use App\Game\Services\ScoutingService;
use App\Models\Game;

class CancelScoutSearch
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $report = $this->scoutingService->getActiveReport($game);

        if ($report) {
            $this->scoutingService->cancelSearch($report);
        }

        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.scout_search_cancelled'));
    }
}
