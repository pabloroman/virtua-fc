<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;

class ShowPlayerDetail
{
    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $gamePlayer = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        return view('partials.player-detail', [
            'game' => $game,
            'gamePlayer' => $gamePlayer,
        ]);
    }
}
