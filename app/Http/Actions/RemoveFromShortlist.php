<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;

class RemoveFromShortlist
{
    public function __invoke(string $gameId, string $playerId)
    {
        Game::findOrFail($gameId);
        $gamePlayer = GamePlayer::where('game_id', $gameId)->findOrFail($playerId);

        ShortlistedPlayer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->delete();

        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.shortlist_removed', ['player' => $gamePlayer->name]));
    }
}
