<?php

namespace App\Http\Views;

use App\Game\Services\ScoutingService;
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

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('scouting', [
            'game' => $game,
            'report' => $report,
            'scoutedPlayers' => $scoutedPlayers,
            'teamCountry' => $game->team->country,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
        ]);
    }
}
