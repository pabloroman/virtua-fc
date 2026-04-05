<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Competition\Services\StandingsCalculator;
use Illuminate\Support\Facades\Log;

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

        Log::channel('standings')->info('[ListenerPath] UpdateLeagueStandings fired', [
            'match_id' => $match->id,
            'competition_id' => $match->competition_id,
            'is_league' => $competition?->isLeague(),
            'is_cup_tie' => $isCupTie,
            'standings_applied' => $match->standings_applied,
            'home_team' => $match->home_team_id,
            'away_team' => $match->away_team_id,
            'score' => $match->home_score . '-' . $match->away_score,
        ]);

        if (! $competition?->isLeague() || $isCupTie) {
            return;
        }

        // Idempotency guard: skip if standings were already applied for this match
        // (prevents double-counting from concurrent finalization or safety net re-entry)
        if ($match->standings_applied) {
            Log::channel('standings')->warning('[ListenerPath] SKIPPED — standings already applied', [
                'match_id' => $match->id,
            ]);

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

        $this->standingsCalculator->recalculatePositions($event->game->id, $match->competition_id, updatePrevPosition: false);

        $match->update(['standings_applied' => true]);

        Log::channel('standings')->info('[ListenerPath] Applied standings + marked applied', [
            'match_id' => $match->id,
        ]);
    }
}
