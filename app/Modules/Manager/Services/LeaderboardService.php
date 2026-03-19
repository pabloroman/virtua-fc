<?php

namespace App\Modules\Manager\Services;

use App\Models\ManagerStats;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            ->where('users.is_profile_public', true)
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
            ->where('users.is_profile_public', true)
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
            ->where('users.is_profile_public', true)
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
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('users.is_profile_public', true)
            ->count();

        $totalMatches = ManagerStats::join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('users.is_profile_public', true)
            ->sum('matches_played');

        return [
            'totalManagers' => $totalManagers,
            'totalMatches' => (int) $totalMatches,
        ];
    }
}
