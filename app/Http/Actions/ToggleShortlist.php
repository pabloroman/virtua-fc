<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use Illuminate\Http\Request;

class ToggleShortlist
{
    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $gamePlayer = GamePlayer::where('game_id', $gameId)->findOrFail($playerId);

        $existing = ShortlistedPlayer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->first();

        if ($existing) {
            $existing->delete();
            $message = __('messages.shortlist_removed', ['player' => $gamePlayer->name]);
        } else {
            ShortlistedPlayer::create([
                'game_id' => $gameId,
                'game_player_id' => $playerId,
                'added_at' => $game->current_date,
            ]);
            $message = __('messages.shortlist_added', ['player' => $gamePlayer->name]);
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }
}
