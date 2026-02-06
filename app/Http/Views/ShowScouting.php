<?php

namespace App\Http\Views;

use App\Game\Services\ScoutingService;
use App\Models\Competition;
use App\Models\Game;

class ShowScouting
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        $report = $this->scoutingService->getActiveReport($game);

        $scoutedPlayers = collect();
        if ($report && $report->isCompleted()) {
            $scoutedPlayers = $report->players;
        }

        // Get available leagues for the search form
        $leagues = Competition::where('type', 'league')->get();

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('scouting', [
            'game' => $game,
            'report' => $report,
            'scoutedPlayers' => $scoutedPlayers,
            'leagues' => $leagues,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
        ]);
    }
}
