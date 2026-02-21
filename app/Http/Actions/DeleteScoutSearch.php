<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\ScoutReport;

class DeleteScoutSearch
{
    public function __invoke(string $gameId, string $reportId)
    {
        Game::findOrFail($gameId);

        $report = ScoutReport::where('game_id', $gameId)
            ->where('status', ScoutReport::STATUS_COMPLETED)
            ->findOrFail($reportId);

        $report->delete();

        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.scout_search_deleted'));
    }
}
