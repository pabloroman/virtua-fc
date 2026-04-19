<?php

namespace App\Http\Actions;

use App\Models\Game;

class EnterFastMode
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Can't enter fast mode while a live match is pending finalization —
        // the user still needs to dismiss that screen first.
        if ($game->pending_finalization_match_id) {
            return redirect()->route('show-game', $gameId)
                ->with('warning', __('messages.fast_mode_blocked_live_match'));
        }

        if (! $game->isFastMode()) {
            $game->update(['fast_mode' => true]);
        }

        return redirect()->route('game.fast-mode', $gameId)
            ->with('info', __('messages.fast_mode_enabled'));
    }
}
