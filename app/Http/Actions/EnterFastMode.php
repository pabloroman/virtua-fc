<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\FastModeService;

class EnterFastMode
{
    public function __construct(
        private readonly FastModeService $fastModeService,
        private readonly AdvanceFastMatchday $advanceFastMatchday,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Fast mode is disabled in tournament mode — the whole point of
        // tournament mode is to play every match manually.
        if ($game->isTournamentMode()) {
            return redirect()->route('show-game', $gameId)
                ->with('warning', __('messages.fast_mode_blocked_tournament'));
        }

        // Can't enter fast mode while a live match is pending finalization —
        // the user still needs to dismiss that screen first.
        if ($game->pending_finalization_match_id) {
            return redirect()->route('show-game', $gameId)
                ->with('warning', __('messages.fast_mode_blocked_live_match'));
        }

        $this->fastModeService->enter($game);

        // Simulate the first match immediately so the user lands on a
        // populated view (last result + updated standings) instead of an
        // empty "simulate your first match" screen.
        return ($this->advanceFastMatchday)($gameId);
    }
}
