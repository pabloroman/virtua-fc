<?php

namespace App\Modules\Manager\Services;

use App\Models\Game;
use App\Models\ManagerStats;
use App\Models\ManagerTrophy;

/**
 * Assembles the compact "career so far" strip shown on the Reputation page.
 * All facts are derived from existing per-game records so the strip is
 * meaningful from the first match onward. Cross-season history (highest
 * league finish, promotions) is deferred to a dedicated history surface.
 */
class CareerSummaryService
{
    /**
     * @return array{
     *   seasons_managed:int,
     *   matches_played:int,
     *   trophies:int,
     *   starting_tier:string,
     * }
     */
    public function build(Game $game, int $userId): array
    {
        $stats = ManagerStats::where('user_id', $userId)
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $trophies = ManagerTrophy::where('user_id', $userId)
            ->where('game_id', $game->id)
            ->count();

        $seasonsCompleted = (int) ($stats?->seasons_completed ?? 0);

        return [
            'seasons_managed' => $seasonsCompleted + 1,
            'matches_played' => (int) ($stats?->matches_played ?? 0),
            'trophies' => $trophies,
            'starting_tier' => $game->team?->clubProfile?->reputation_level ?? 'local',
        ];
    }
}
