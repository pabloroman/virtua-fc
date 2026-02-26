<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\DB;

class UpdateGoalkeeperStats
{
    public function handle(MatchFinalized $event): void
    {
        $match = $event->match;
        $homeLineupIds = $match->home_lineup ?? [];
        $awayLineupIds = $match->away_lineup ?? [];
        $allLineupIds = array_merge($homeLineupIds, $awayLineupIds);

        $goalkeepers = GamePlayer::whereIn('id', $allLineupIds)
            ->where('position', 'Goalkeeper')
            ->get();

        if ($goalkeepers->isEmpty()) {
            return;
        }

        $increments = [];
        foreach ($goalkeepers as $gk) {
            $increments[$gk->id] = ['goals_conceded' => 0, 'clean_sheets' => 0];

            if (in_array($gk->id, $homeLineupIds)) {
                $increments[$gk->id]['goals_conceded'] = $match->away_score;
                if ($match->away_score === 0) {
                    $increments[$gk->id]['clean_sheets'] = 1;
                }
            } elseif (in_array($gk->id, $awayLineupIds)) {
                $increments[$gk->id]['goals_conceded'] = $match->home_score;
                if ($match->home_score === 0) {
                    $increments[$gk->id]['clean_sheets'] = 1;
                }
            }
        }

        // Filter out goalkeepers with no changes
        $increments = array_filter($increments, fn ($v) => $v['goals_conceded'] !== 0 || $v['clean_sheets'] !== 0);

        if (empty($increments)) {
            return;
        }

        $ids = array_keys($increments);
        $idList = "'" . implode("','", $ids) . "'";
        $setClauses = [];

        foreach (['goals_conceded', 'clean_sheets'] as $column) {
            $cases = [];
            foreach ($increments as $gkId => $values) {
                if ($values[$column] !== 0) {
                    $cases[] = "WHEN id = '{$gkId}' THEN {$column} + {$values[$column]}";
                }
            }
            if (! empty($cases)) {
                $setClauses[] = "{$column} = CASE " . implode(' ', $cases) . " ELSE {$column} END";
            }
        }

        if (! empty($setClauses)) {
            DB::statement('UPDATE game_players SET ' . implode(', ', $setClauses) . " WHERE id IN ({$idList})");
        }
    }
}
