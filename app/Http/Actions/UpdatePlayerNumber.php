<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdatePlayerNumber
{
    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);

        $gamePlayer = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        $validated = $request->validate([
            'number' => [
                'required',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('game_players', 'number')
                    ->where('game_id', $gameId)
                    ->where('team_id', $game->team_id)
                    ->ignore($gamePlayer->id),
            ],
        ], [
            'number.required' => __('squad.number_invalid'),
            'number.unique' => __('squad.number_taken'),
            'number.min' => __('squad.number_invalid'),
            'number.max' => __('squad.number_invalid'),
            'number.integer' => __('squad.number_invalid'),
        ]);

        try {
            $gamePlayer->update(['number' => $validated['number']]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'success' => false,
                'message' => __('squad.number_taken'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'number' => $gamePlayer->number,
            'message' => __('squad.number_updated'),
        ]);
    }
}
