<?php

namespace App\Http\Views;

use App\Game\Services\LineupService;
use App\Models\AcademyPlayer;
use App\Models\Game;

class ShowSquad
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get all players for the user's team, grouped by position
        $players = $this->lineupService->getPlayersByPositionGroup($gameId, $game->team_id);

        $academyCount = AcademyPlayer::where('game_id', $gameId)->where('team_id', $game->team_id)->count();

        return view('squad', [
            'game' => $game,
            'goalkeepers' => $players['goalkeepers'],
            'defenders' => $players['defenders'],
            'midfielders' => $players['midfielders'],
            'forwards' => $players['forwards'],
            'isTransferWindow' => $game->isTransferWindowOpen(),
            'academyCount' => $academyCount,
        ]);
    }
}
