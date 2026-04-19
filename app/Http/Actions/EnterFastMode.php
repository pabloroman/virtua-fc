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

        // Record the calendar date at entry so the view can hide the "last
        // result" panel until the assistant coach has actually simulated a
        // match on or after this date. Always refresh on (re-)entry so
        // stepping out and back in resets the marker correctly.
        $game->update([
            'fast_mode' => true,
            'fast_mode_entered_on' => $game->current_date?->toDateString(),
        ]);

        return redirect()->route('game.fast-mode', $gameId)
            ->with('info', __('messages.fast_mode_enabled'));
    }
}
