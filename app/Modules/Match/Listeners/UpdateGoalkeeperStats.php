<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Models\GamePlayer;

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

        foreach ($goalkeepers as $gk) {
            if (in_array($gk->id, $homeLineupIds)) {
                $gk->goals_conceded += $match->away_score;
                if ($match->away_score === 0) {
                    $gk->clean_sheets++;
                }
            } elseif (in_array($gk->id, $awayLineupIds)) {
                $gk->goals_conceded += $match->home_score;
                if ($match->home_score === 0) {
                    $gk->clean_sheets++;
                }
            }
            $gk->save();
        }
    }
}
