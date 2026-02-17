<?php

namespace App\Game\Listeners;

use App\Game\Events\MatchFinalized;
use App\Game\Services\StandingsCalculator;

class UpdateLeagueStandings
{
    public function __construct(
        private readonly StandingsCalculator $standingsCalculator,
    ) {}

    public function handle(MatchFinalized $event): void
    {
        $match = $event->match;
        $competition = $event->competition;
        $isCupTie = $match->cup_tie_id !== null;

        if (! $competition?->isLeague() || $isCupTie) {
            return;
        }

        $this->standingsCalculator->updateAfterMatch(
            gameId: $event->game->id,
            competitionId: $match->competition_id,
            homeTeamId: $match->home_team_id,
            awayTeamId: $match->away_team_id,
            homeScore: $match->home_score,
            awayScore: $match->away_score,
        );

        $this->standingsCalculator->recalculatePositions($event->game->id, $match->competition_id);
    }
}
