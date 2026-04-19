<?php

namespace App\Http\Actions;

use App\Models\Game;

class EnterFastMode
{
    public function __construct(
        private readonly AdvanceFastMatchday $advanceFastMatchday,
    ) {}

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
        // result" panel when a re-entry happens but the immediate advance
        // below can't play (e.g. a pending action blocks it). Always refresh
        // on (re-)entry so stepping out and back in resets the marker.
        $game->update([
            'fast_mode' => true,
            'fast_mode_entered_on' => $game->current_date?->toDateString(),
        ]);

        // Simulate the first match immediately so the user lands on a
        // populated view (last result + updated standings) instead of an
        // empty "simulate your first match" screen. The advance action
        // redirects to the fast-mode view itself, handling blocked/
        // season-complete/error flows along the way.
        return ($this->advanceFastMatchday)($gameId);
    }
}
