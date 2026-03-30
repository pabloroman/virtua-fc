<?php

namespace App\Modules\Competition\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use Carbon\Carbon;
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
            ->orderByDesc('scheduled_date')
            ->limit($limit)
            ->get();

        $form = $matches->map(fn ($match) => $this->getMatchResult($match, $teamId))->toArray();

        // Reverse so oldest is first (left to right chronological)
        return array_reverse($form);
    }

    /**
     * Get all matches for a specific matchday/round.
     *
     * For Swiss-format competitions, round_number overlaps between the league
     * phase and knockout phase, so $roundName disambiguates them.
     */
    public function getMatchdayResults(string $gameId, string $competitionId, int $matchday, ?string $roundName = null): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'events.gamePlayer.player', 'competition', 'mvpPlayer.player'])
            ->where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $matchday)
            ->when($roundName !== null, function ($q) use ($roundName) {
                $q->where('round_name', $roundName);
            })
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
            return ucfirst($match->scheduled_date->locale(app()->getLocale())->translatedFormat('F Y'));
        });
    }

    /**
     * Calculate season stats from played matches for a team.
     */
    public function calculateSeasonStats(Collection $playedMatches, string $teamId): array
    {
        $wins = 0;
        $draws = 0;
        $losses = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;
        $home = ['wins' => 0, 'draws' => 0, 'losses' => 0, 'goalsFor' => 0, 'goalsAgainst' => 0];
        $away = ['wins' => 0, 'draws' => 0, 'losses' => 0, 'goalsFor' => 0, 'goalsAgainst' => 0];
        $form = [];

        foreach ($playedMatches as $match) {
            $isHome = $match->home_team_id === $teamId;
            $yourScore = $isHome ? $match->home_score : $match->away_score;
            $oppScore = $isHome ? $match->away_score : $match->home_score;
            $venue = $isHome ? 'home' : 'away';

            $goalsFor += $yourScore;
            $goalsAgainst += $oppScore;
            $$venue['goalsFor'] += $yourScore;
            $$venue['goalsAgainst'] += $oppScore;

            if ($yourScore > $oppScore) {
                $result = 'W';
                $wins++;
                $$venue['wins']++;
            } elseif ($yourScore < $oppScore) {
                $result = 'L';
                $losses++;
                $$venue['losses']++;
            } else {
                $result = 'D';
                $draws++;
                $$venue['draws']++;
            }

            $form[] = $result;
        }

        $totalMatches = $wins + $draws + $losses;

        return [
            'played' => $totalMatches,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'winPercent' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100) : 0,
            'goalsFor' => $goalsFor,
            'goalsAgainst' => $goalsAgainst,
            'form' => array_slice(array_reverse($form), 0, 5),
            'home' => [
                'played' => $home['wins'] + $home['draws'] + $home['losses'],
                'wins' => $home['wins'],
                'draws' => $home['draws'],
                'losses' => $home['losses'],
                'points' => ($home['wins'] * 3) + $home['draws'],
                'goalsFor' => $home['goalsFor'],
                'goalsAgainst' => $home['goalsAgainst'],
            ],
            'away' => [
                'played' => $away['wins'] + $away['draws'] + $away['losses'],
                'wins' => $away['wins'],
                'draws' => $away['draws'],
                'losses' => $away['losses'],
                'points' => ($away['wins'] * 3) + $away['draws'],
                'goalsFor' => $away['goalsFor'],
                'goalsAgainst' => $away['goalsAgainst'],
            ],
        ];
    }

    /**
     * Get placeholder entries for knockout rounds not yet drawn (WC2026 tournament mode).
     *
     * Returns a collection of stdClass objects with scheduled_date, round_name, and is_placeholder=true,
     * for any knockout round that doesn't have fixtures yet.
     */
    public function getKnockoutPlaceholders(Game $game, string $competitionId): Collection
    {
        $schedulePath = base_path("data/2025/{$competitionId}/schedule.json");

        if (!file_exists($schedulePath)) {
            return collect();
        }

        $schedule = json_decode(file_get_contents($schedulePath), true);
        $knockoutRounds = $schedule['knockout'] ?? [];

        // Find which rounds already have fixtures for this team
        $existingRoundNames = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->whereNotNull('round_name')
            ->pluck('round_name')
            ->unique()
            ->toArray();

        $placeholders = collect();

        foreach ($knockoutRounds as $round) {
            $roundName = $round['name'];

            if (in_array($roundName, $existingRoundNames)) {
                continue;
            }

            $placeholders->push((object) [
                'scheduled_date' => Carbon::parse($round['date']),
                'round_name' => $roundName,
                'is_placeholder' => true,
            ]);
        }

        return $placeholders;
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
