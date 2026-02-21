<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use Illuminate\Http\Request;

class RemoveFromShortlist
{
    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        Game::findOrFail($gameId);
        $gamePlayer = GamePlayer::where('game_id', $gameId)->findOrFail($playerId);

        ShortlistedPlayer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->delete();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'playerId' => $playerId]);
        }

        return redirect()->route('game.scouting', $gameId)
            ->with('success', __('messages.shortlist_removed', ['player' => $gamePlayer->name]));
    }
}
