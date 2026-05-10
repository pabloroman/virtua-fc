<?php

namespace App\Modules\Manager\Services;

use App\Models\Team;
use App\Models\TournamentSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NationalTeamStatsService
{
    /**
     * Get all national teams that appear in tournament summaries, with tournament counts.
     */
    public function getTeamsWithTournamentCounts(): Collection
    {
        return Team::query()
            ->join('tournament_summaries', 'teams.id', '=', 'tournament_summaries.team_id')
            ->where('teams.type', 'national')
            ->whereNotNull('teams.slug')
            ->groupBy('teams.id')
            ->selectRaw('teams.*, COUNT(*) as tournaments_count')
            ->orderByDesc('tournaments_count')
            ->get();
    }

    /**
     * Get aggregate stats for the index page.
     */
    public function getIndexAggregateStats(): array
    {
        $base = TournamentSummary::query()
            ->join('teams', 'teams.id', '=', 'tournament_summaries.team_id')
            ->where('teams.type', 'national');

        return [
            'totalTeams' => (clone $base)->distinct('tournament_summaries.team_id')->count('tournament_summaries.team_id'),
            'totalTournaments' => (clone $base)->count(),
        ];
    }

    /**
     * Get aggregate stats for a specific team.
     */
    public function getTeamStats(string $teamId): array
    {
        $query = TournamentSummary::where('team_id', $teamId);

        return [
            'tournamentsPlayed' => (clone $query)->count(),
            'titles' => (clone $query)->where('is_champion', true)->count(),
            'totalMatches' => (int) (clone $query)->sum('matches_played'),
            'totalWins' => (int) (clone $query)->sum('matches_won'),
            'totalDraws' => (int) (clone $query)->sum('matches_drawn'),
            'totalLosses' => (int) (clone $query)->sum('matches_lost'),
            'goalsScored' => (int) (clone $query)->sum('goals_scored'),
            'goalsConceded' => (int) (clone $query)->sum('goals_conceded'),
        ];
    }

    /**
     * Get result distribution for a specific team.
     * Returns array of ['label' => string, 'count' => int, 'percentage' => float].
     */
    public function getResultDistribution(string $teamId): array
    {
        $results = TournamentSummary::where('team_id', $teamId)
            ->groupBy('result_label')
            ->selectRaw('result_label, COUNT(*) as count')
            ->pluck('count', 'result_label');

        $total = $results->sum();
        $allLabels = ['champion', 'runner_up', 'semi_finalist', 'quarter_finalist', 'round_of_16', 'round_of_32', 'group_stage'];

        return collect($allLabels)->map(fn ($label) => [
            'label' => $label,
            'count' => $results->get($label, 0),
            'percentage' => $total > 0 ? round(($results->get($label, 0) / $total) * 100, 1) : 0,
        ])->all();
    }

    /**
     * Get player selection frequency for a team across all tournaments.
     *
     * Streams summaries with `lazy()` and pulls only the `your_squad_stats`
     * JSON sub-key — loading the full `summary_data` blobs for popular teams
     * (e.g. Spain, thousands of tournaments) blows up PHP memory.
     *
     * Groups by player_id when available (new snapshots), falls back to player_name (legacy).
     */
    public function getPlayerFrequency(string $teamId): Collection
    {
        $playerAgg = [];
        $nameToId = []; // Maps player_name → player_id for merging legacy entries
        $totalTournaments = 0;

        DB::connection('pgsql_control')
            ->table('tournament_summaries')
            ->where('team_id', $teamId)
            ->selectRaw("id, summary_data->'your_squad_stats' as squad_stats")
            ->orderBy('id')
            ->lazy(200)
            ->each(function ($row) use (&$playerAgg, &$nameToId, &$totalTournaments) {
                $totalTournaments++;
                $squadStats = $row->squad_stats ? json_decode($row->squad_stats, true) : [];

                foreach ($squadStats as $player) {
                    $playerId = $player['player_id'] ?? null;
                    $playerName = $player['player_name'];

                    if ($playerId) {
                        // If we previously aggregated this player by name, merge into the ID key
                        if (isset($playerAgg[$playerName]) && !isset($playerAgg[$playerId])) {
                            $playerAgg[$playerId] = $playerAgg[$playerName];
                            unset($playerAgg[$playerName]);
                        }
                        $nameToId[$playerName] = $playerId;
                        $key = $playerId;
                    } else {
                        // Legacy entry without player_id — use ID if we've seen this name before
                        $key = $nameToId[$playerName] ?? $playerName;
                    }

                    if (!isset($playerAgg[$key])) {
                        $playerAgg[$key] = [
                            'player_name' => $playerName,
                            'position' => $player['position'],
                            'times_selected' => 0,
                            'total_appearances' => 0,
                            'total_goals' => 0,
                            'total_assists' => 0,
                        ];
                    }

                    $playerAgg[$key]['times_selected']++;
                    $playerAgg[$key]['total_appearances'] += $player['appearances'] ?? 0;
                    $playerAgg[$key]['total_goals'] += $player['goals'] ?? 0;
                    $playerAgg[$key]['total_assists'] += $player['assists'] ?? 0;
                }
            });

        if ($totalTournaments === 0) {
            return collect();
        }

        return collect($playerAgg)
            ->map(fn ($p) => [
                ...$p,
                'total_tournaments' => $totalTournaments,
                'percentage' => round(($p['times_selected'] / $totalTournaments) * 100, 1),
            ])
            ->sortByDesc('percentage')
            ->values();
    }
}
