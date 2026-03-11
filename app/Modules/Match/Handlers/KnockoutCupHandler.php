<?php

namespace App\Modules\Match\Handlers;

use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class KnockoutCupHandler extends CupCompetitionHandler
{
    public function getType(): string
    {
        return 'knockout_cup';
    }

    /**
     * Get all unplayed cup matches from the same date.
     * Cup matches are grouped by date, not round number.
     */
    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'cupTie'])
            ->where('game_id', $gameId)
            ->whereDate('scheduled_date', $nextMatch->scheduled_date->toDateString())
            ->whereNotNull('cup_tie_id')
            ->where('played', false)
            ->get();
    }

    /**
     * No pre-match actions needed - draws now happen after rounds complete.
     */
    public function beforeMatches(Game $game, string $targetDate): void
    {
        // Draws are now conducted after rounds complete, not before matches
    }

    /**
     * Resolve cup ties after matches have been played.
     */
    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        $this->resolveCompletedTies($game, $matches, $allPlayers);

        $competitionId = $matches->first()?->competition_id;
        if ($competitionId) {
            $this->maybeResetYellowCards($game->id, $competitionId, 'knockout_cup');
        }
    }
}
