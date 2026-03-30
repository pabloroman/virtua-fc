<?php

namespace App\Modules\Manager\Services;

use App\Models\Team;
use App\Models\TournamentSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Locale;

class TournamentLeaderboardService
{
    private const PER_PAGE = 50;

    private const ALLOWED_SORTS = [
        'tournaments_won',
        'best_finish',
        'total_tournaments',
        'win_rate',
        'goals_scored',
    ];

    public function normalizeSort(string $sort): string
    {
        return in_array($sort, self::ALLOWED_SORTS) ? $sort : 'tournaments_won';
    }

    public function getRankings(string $sort, ?string $country, ?string $teamId): LengthAwarePaginator
    {
        $query = TournamentSummary::query()
            ->join('users', 'users.id', '=', 'tournament_summaries.user_id')
            ->groupBy(
                'tournament_summaries.user_id',
                'users.name',
                'users.username',
                'users.avatar',
                'users.country',
            )
            ->select(
                'tournament_summaries.user_id',
                'users.name',
                'users.username',
                'users.avatar',
                'users.country',
                DB::raw('COUNT(*) as total_tournaments'),
                DB::raw('SUM(CASE WHEN tournament_summaries.is_champion THEN 1 ELSE 0 END) as tournaments_won'),
                DB::raw('MAX(tournament_summaries.result_points) as best_finish'),
                DB::raw('SUM(tournament_summaries.matches_played) as total_matches'),
                DB::raw('SUM(tournament_summaries.matches_won) as total_wins'),
                DB::raw('SUM(tournament_summaries.matches_drawn) as total_draws'),
                DB::raw('SUM(tournament_summaries.matches_lost) as total_losses'),
                DB::raw('SUM(tournament_summaries.goals_scored) as total_goals'),
                DB::raw('ROUND(SUM(tournament_summaries.matches_won) * 100.0 / NULLIF(SUM(tournament_summaries.matches_played), 0), 1) as win_rate'),
            );

        if ($country) {
            $query->where('users.country', $country);
        }

        if ($teamId) {
            $query->where('tournament_summaries.team_id', $teamId);
        }

        $orderColumn = match ($sort) {
            'tournaments_won' => 'tournaments_won',
            'best_finish' => 'best_finish',
            'total_tournaments' => 'total_tournaments',
            'win_rate' => 'win_rate',
            'goals_scored' => 'total_goals',
            default => 'tournaments_won',
        };

        return $query->orderByDesc($orderColumn)
            ->orderByDesc('total_tournaments')
            ->paginate(self::PER_PAGE);
    }

    public function getAggregateStats(): array
    {
        $totalPlayers = TournamentSummary::distinct('user_id')->count('user_id');
        $totalTournaments = TournamentSummary::count();

        return [
            'totalPlayers' => $totalPlayers,
            'totalTournaments' => $totalTournaments,
        ];
    }

    public function getCountries(): array
    {
        $locale = app()->getLocale();

        $countryCodes = TournamentSummary::query()
            ->join('users', 'users.id', '=', 'tournament_summaries.user_id')
            ->whereNotNull('users.country')
            ->where('users.country', '!=', '')
            ->distinct()
            ->pluck('users.country');

        return $countryCodes->mapWithKeys(function ($code) use ($locale) {
            $localized = Locale::getDisplayRegion('und_'.$code, $locale);

            return [$code => ($localized !== $code) ? $localized : $code];
        })->sort()->toArray();
    }

    public function getTeamsPlayed(): Collection
    {
        return Team::query()
            ->join('tournament_summaries', 'teams.id', '=', 'tournament_summaries.team_id')
            ->groupBy('teams.id', 'teams.name', 'teams.image')
            ->select('teams.id', 'teams.name', 'teams.image')
            ->orderBy('teams.name')
            ->get();
    }
}
