<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Resets league standings for the new season.
 * Preserves team positions from previous season for initial ordering.
 * Priority: 40 (runs last)
 */
class StandingsResetProcessor implements SeasonEndProcessor
{
    public function priority(): int
    {
        return 40;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Store final positions for metadata
        $finalStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $data->competitionId)
            ->orderBy('position')
            ->get();

        $data->setMetadata('finalStandings', $finalStandings->map(fn ($s) => [
            'position' => $s->position,
            'teamId' => $s->team_id,
            'points' => $s->points,
            'goalDifference' => $s->goal_difference,
        ])->toArray());

        // Reset all standings - keep positions from last season
        foreach ($finalStandings as $standing) {
            $standing->update([
                'prev_position' => null,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        return $data;
    }
}
