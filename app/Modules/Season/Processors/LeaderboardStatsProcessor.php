<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\ManagerStats;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

/**
 * Increments the seasons_completed counter on the user's leaderboard stats.
 * Priority: 4 (runs early in the closing pipeline).
 */
class LeaderboardStatsProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 15;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $stats = ManagerStats::firstOrCreate(
            ['game_id' => $game->id],
            ['user_id' => $game->user_id, 'team_id' => $game->team_id],
        );

        $stats->increment('seasons_completed');

        return $data;
    }
}
