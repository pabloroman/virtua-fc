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

        $alreadyInFastMode = $game->isFastMode();
        $this->fastModeService->enter($game);

        // Simulate the first match immediately so the user lands on a
        // populated view (last result + updated standings) instead of an
        // empty "simulate your first match" screen.
        $playedBefore = $game->matches()->where('played', true)->count();
        $response = ($this->advanceFastMatchday)($gameId);
        $playedAfter = $game->matches()->where('played', true)->count();

        // If the inline advance didn't actually simulate anything (claim
        // contention, swallowed exception, etc.), the user would land on
        // game.fast-mode with fast_mode_entered_on snapshotted past every
        // previously-played match — an empty "last result" panel with no
        // explanation. Roll back the entry and surface a visible warning
        // on show-game, where flash messages render. Skip the rollback if
        // the user was already in fast mode before this request: we don't
        // want to kick a healthy session out on a transient retry.
        if ($playedAfter === $playedBefore && ! $alreadyInFastMode) {
            $this->fastModeService->exit($game->fresh());
            return redirect()->route('show-game', $gameId)
                ->with('warning', __('messages.fast_mode_advance_failed_retry'));
        }

        return $response;
    }
}
