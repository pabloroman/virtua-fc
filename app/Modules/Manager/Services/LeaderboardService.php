<?php

namespace App\Modules\Manager\Services;

use App\Models\ManagerStats;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Locale;

class LeaderboardService
{
    public const MIN_MATCHES = 10;
    private const PER_PAGE = 50;

    private const ALLOWED_SORTS = [
        'win_percentage',
        'longest_unbeaten_streak',
        'matches_played',
        'seasons_completed',
    ];

    /**
     * Validate and normalize the sort column.
     */
    public function normalizeSort(string $sort): string
    {
        return in_array($sort, self::ALLOWED_SORTS) ? $sort : 'win_percentage';
    }

    /**
     * Get the paginated leaderboard rankings.
     */
    public function getRankings(string $sort, ?string $country, ?string $province): LengthAwarePaginator
    {
        $query = ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'manager_stats.team_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->select('manager_stats.*', 'users.name', 'users.username', 'users.avatar', 'users.country', 'users.province', 'teams.name as team_name', 'teams.image as team_image');

        if ($country) {
            $query->where('users.country', $country);
        }

        if ($province && $country) {
            $query->where('users.province', $province);
        }

        return $query->orderByDesc("manager_stats.{$sort}")
            ->orderByDesc('manager_stats.matches_played')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Get provinces with qualifying managers for a given country.
     */
    public function getProvincesForCountry(string $country): array
    {
        return ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('users.country', $country)
            ->whereNotNull('users.province')
            ->where('users.province', '!=', '')
            ->distinct()
            ->orderBy('users.province')
            ->pluck('users.province')
            ->toArray();
    }

    /**
     * Get all countries with qualifying managers, localized.
     */
    public function getCountries(): array
    {
        $locale = app()->getLocale();

        $countryCodes = ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->whereNotNull('users.country')
            ->where('users.country', '!=', '')
            ->distinct()
            ->pluck('users.country');

        return $countryCodes->mapWithKeys(function ($code) use ($locale) {
            $localized = Locale::getDisplayRegion('und_'.$code, $locale);

            return [$code => ($localized !== $code) ? $localized : $code];
        })->sort()->toArray();
    }

    /**
     * Get aggregate leaderboard stats (total qualifying managers, total matches).
     */
    public function getAggregateStats(): array
    {
        $totalManagers = ManagerStats::where('matches_played', '>=', self::MIN_MATCHES)
            ->count();

        $totalMatches = ManagerStats::sum('matches_played');

        return [
            'totalManagers' => $totalManagers,
            'totalMatches' => (int) $totalMatches,
        ];
    }

    /**
     * Get all club teams that have at least one qualifying manager, with manager counts.
     */
    public function getTeamsWithManagers(): Collection
    {
        return Team::query()
            ->join('manager_stats', 'teams.id', '=', 'manager_stats.team_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->where('teams.type', 'club')
            ->where('teams.is_placeholder', false)
            ->whereNull('teams.parent_team_id')
            ->groupBy('teams.id', 'teams.name', 'teams.slug', 'teams.image', 'teams.transfermarkt_id', 'teams.type', 'teams.country')
            ->selectRaw('teams.id, teams.name, teams.slug, teams.image, teams.transfermarkt_id, teams.type, teams.country, count(distinct manager_stats.user_id) as managers_count')
            ->orderBy('teams.name')
            ->get();
    }

    /**
     * Get paginated leaderboard rankings filtered by team.
     */
    public function getRankingsForTeam(string $teamId, string $sort): LengthAwarePaginator
    {
        return ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'manager_stats.team_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->where('manager_stats.team_id', $teamId)
            ->select('manager_stats.*', 'users.name', 'users.username', 'users.avatar', 'users.country', 'users.province', 'teams.name as team_name', 'teams.image as team_image')
            ->orderByDesc("manager_stats.{$sort}")
            ->orderByDesc('manager_stats.matches_played')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Get aggregate stats for a specific team.
     */
    public function getTeamAggregateStats(string $teamId): array
    {
        $query = ManagerStats::query()
            ->where('manager_stats.team_id', $teamId);

        $totalManagers = (clone $query)
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->count();

        $totalMatches = (int) $query->sum('matches_played');

        return [
            'totalManagers' => $totalManagers,
            'totalMatches' => $totalMatches,
        ];
    }
}
