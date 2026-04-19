<?php

namespace App\Modules\Match\Services;

use App\Models\Game;
use App\Models\GameMatch;

class FastModeService
{
    public function enter(Game $game): void
    {
        // Reset fast_mode_entered_on on every (re-)entry so stepping out and
        // back in clears the "last result" panel — the user should land on
        // an empty session. The not-null marker also doubles as the
        // is-fast-mode flag (Game::isFastMode()).
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
            'goalEvents.gamePlayer.player',
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
