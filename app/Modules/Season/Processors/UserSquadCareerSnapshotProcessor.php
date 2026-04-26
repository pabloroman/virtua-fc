<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\UserSquadCareerRecord;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots the just-finished season's match-state stats into each user-owned
 * career record's season_stats JSON.
 *
 * Runs before StatsResetProcessor (priority 65) so the counters on
 * GamePlayerMatchState are still populated when we read them.
 */
class UserSquadCareerSnapshotProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 60;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $seasonKey = (string) (int) $game->season;

        $rows = DB::table('user_squad_career_records as r')
            ->leftJoin('game_player_match_state as ms', 'ms.game_player_id', '=', 'r.game_player_id')
            ->leftJoin('game_players as gp', 'gp.id', '=', 'r.game_player_id')
            ->where('r.game_id', $game->id)
            ->select([
                'r.id',
                'r.season_stats',
                'gp.team_id',
                'ms.appearances',
                'ms.season_appearances',
                'ms.goals',
                'ms.own_goals',
                'ms.assists',
                'ms.yellow_cards',
                'ms.red_cards',
                'ms.goals_conceded',
                'ms.clean_sheets',
            ])
            ->get();

        foreach ($rows as $row) {
            $stats = is_string($row->season_stats) ? json_decode($row->season_stats, true) : (array) $row->season_stats;
            if (! is_array($stats)) {
                $stats = [];
            }

            $stats[$seasonKey] = [
                'appearances' => (int) ($row->appearances ?? 0),
                'season_appearances' => (int) ($row->season_appearances ?? 0),
                'goals' => (int) ($row->goals ?? 0),
                'own_goals' => (int) ($row->own_goals ?? 0),
                'assists' => (int) ($row->assists ?? 0),
                'yellow_cards' => (int) ($row->yellow_cards ?? 0),
                'red_cards' => (int) ($row->red_cards ?? 0),
                'goals_conceded' => (int) ($row->goals_conceded ?? 0),
                'clean_sheets' => (int) ($row->clean_sheets ?? 0),
                'team_id' => $row->team_id,
            ];

            UserSquadCareerRecord::where('id', $row->id)->update([
                'season_stats' => json_encode($stats),
            ]);
        }

        return $data;
    }
}
