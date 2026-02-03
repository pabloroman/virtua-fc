<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class CalendarService
{
    /**
     * Get all fixtures for a team, ordered by date.
     */
    public function getTeamFixtures(Game $game): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $game->id)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Get upcoming (unplayed) fixtures for a team.
     */
    public function getUpcomingFixtures(Game $game, int $limit = 5): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $game->id)
            ->where('played', false)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->orderBy('scheduled_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent form for a team (W/D/L results).
     */
    public function getTeamForm(string $gameId, string $teamId, int $limit = 5): array
    {
        $matches = GameMatch::where('game_id', $gameId)
            ->where('played', true)
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->orderByDesc('played_at')
            ->limit($limit)
            ->get();

        $form = $matches->map(fn ($match) => $this->getMatchResult($match, $teamId))->toArray();

        // Reverse so oldest is first (left to right chronological)
        return array_reverse($form);
    }

    /**
     * Get all matches for a specific matchday/round.
     */
    public function getMatchdayResults(string $gameId, string $competitionId, int $matchday): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'events.gamePlayer.player', 'competition'])
            ->where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $matchday)
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Find the player's match within a collection of matches.
     */
    public function findPlayerMatch(Collection $matches, string $teamId): ?GameMatch
    {
        return $matches->first(function ($match) use ($teamId) {
            return $match->home_team_id === $teamId
                || $match->away_team_id === $teamId;
        });
    }

    /**
     * Group fixtures by month for calendar display.
     */
    public function groupByMonth(Collection $fixtures): Collection
    {
        return $fixtures->groupBy(function ($match) {
            return $match->scheduled_date->format('F Y');
        });
    }

    /**
     * Get match result for a team (W/D/L).
     */
    private function getMatchResult(GameMatch $match, string $teamId): string
    {
        $isHome = $match->home_team_id === $teamId;
        $teamScore = $isHome ? $match->home_score : $match->away_score;
        $opponentScore = $isHome ? $match->away_score : $match->home_score;

        if ($teamScore > $opponentScore) {
            return 'W';
        }

        if ($teamScore < $opponentScore) {
            return 'L';
        }

        return 'D';
    }
}
