<?php

namespace App\Modules\Match\Listeners;

use App\Models\GameStanding;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Competition\Services\StandingsCalculator;

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

        $this->appendForm($event->game->id, $match->competition_id, $match->home_team_id, $match->away_team_id, $match->home_score, $match->away_score);
    }

    private function appendForm(string $gameId, string $competitionId, string $homeTeamId, string $awayTeamId, int $homeScore, int $awayScore): void
    {
        $homeChar = $homeScore > $awayScore ? 'W' : ($homeScore < $awayScore ? 'L' : 'D');
        $awayChar = $awayScore > $homeScore ? 'W' : ($awayScore < $homeScore ? 'L' : 'D');

        $standings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereIn('team_id', [$homeTeamId, $awayTeamId])
            ->get()
            ->keyBy('team_id');

        foreach ([[$homeTeamId, $homeChar], [$awayTeamId, $awayChar]] as [$teamId, $char]) {
            $standing = $standings[$teamId] ?? null;
            if ($standing) {
                $standing->form = substr(($standing->form ?? '') . $char, -5);
                $standing->save();
            }
        }
    }
}
