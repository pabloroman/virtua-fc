<?php

namespace App\Modules\Match\Listeners;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\ManagerStats;
use App\Modules\Match\Events\MatchFinalized;

class UpdateManagerStats
{
    public function handle(MatchFinalized $event): void
    {
        $match = $event->match;
        $game = $event->game;

        // Only career mode
        if (! $game->isCareerMode()) {
            return;
        }

        // Only matches involving the user's team
        if (! $match->involvesTeam($game->team_id)) {
            return;
        }

        $result = $this->determineResult($match, $game->team_id);

        if ($result === null) {
            return;
        }

        $stats = ManagerStats::firstOrCreate(
            ['game_id' => $game->id],
            ['user_id' => $game->user_id, 'team_id' => $game->team_id],
        );

        $stats->recordResult($result);
    }

    /**
     * Determine the match result for the given team.
     *
     * For cup matches with extra time or penalties, uses the extended result.
     * For all other matches, uses the 90-minute score.
     *
     * @return 'win'|'draw'|'loss'|null
     */
    private function determineResult(GameMatch $match, string $teamId): ?string
    {
        if (! $match->played) {
            return null;
        }

        $isHome = $match->isHomeTeam($teamId);

        // Check penalties first
        if ($match->home_score_penalties !== null && $match->away_score_penalties !== null) {
            $teamPenalties = $isHome ? $match->home_score_penalties : $match->away_score_penalties;
            $opponentPenalties = $isHome ? $match->away_score_penalties : $match->home_score_penalties;

            return $teamPenalties > $opponentPenalties ? 'win' : 'loss';
        }

        // Check extra time
        if ($match->is_extra_time && $match->home_score_et !== null && $match->away_score_et !== null) {
            $teamScore = $isHome ? $match->home_score_et : $match->away_score_et;
            $opponentScore = $isHome ? $match->away_score_et : $match->home_score_et;

            if ($teamScore > $opponentScore) {
                return 'win';
            }
            if ($opponentScore > $teamScore) {
                return 'loss';
            }

            return 'draw';
        }

        // Regular time
        $teamScore = $isHome ? $match->home_score : $match->away_score;
        $opponentScore = $isHome ? $match->away_score : $match->home_score;

        if ($teamScore > $opponentScore) {
            return 'win';
        }
        if ($opponentScore > $teamScore) {
            return 'loss';
        }

        return 'draw';
    }
}
