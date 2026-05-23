<?php

namespace App\Modules\Match\Services;

use App\Models\Game;
use App\Models\GameMatch;

class FastModeService
{
    public function enter(Game $game): void
    {
        // No-op when already in fast mode. current_date is forward-looking
        // (points to the next unplayed match), so re-snapshotting it mid-
        // session would advance the marker past the just-played match and
        // hide the "last result" panel. Exiting first (which nulls the
        // column) is the supported way to clear the session.
        if ($game->fast_mode_entered_on !== null) {
            return;
        }

        $game->update([
            'fast_mode_entered_on' => $game->current_date?->toDateString(),
        ]);
    }

    public function exit(Game $game): void
    {
        $game->update(['fast_mode_entered_on' => null]);
    }

    /**
     * Last match the player's team played, scoped to the current fast-mode
     * session. Without the date scope the panel would resurrect the last
     * manually-played match the first time the user lands on the view.
     */
    public function getLastPlayerMatch(Game $game): ?GameMatch
    {
        $query = GameMatch::with([
            'homeTeam',
            'awayTeam',
            'competition',
            'events.gamePlayer',
            'mvpPlayer',
        ])
            ->where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id));

        if ($game->fast_mode_entered_on) {
            $query->where('scheduled_date', '>=', $game->fast_mode_entered_on->toDateString());
        }

        /** @var GameMatch|null */
        return $query->orderByDesc('scheduled_date')->first();
    }
}
