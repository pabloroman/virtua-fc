<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameTacticalPreset;
use Illuminate\Http\Request;

class DeleteTacticalPreset
{
    public function __invoke(Request $request, string $gameId, string $presetId)
    {
        Game::findOrFail($gameId);

        GameTacticalPreset::where('id', $presetId)
            ->where('game_id', $gameId)
            ->firstOrFail()
            ->delete();

        return redirect()->route('game.lineup', $gameId)
            ->with('success', __('messages.preset_deleted'));
    }
}
