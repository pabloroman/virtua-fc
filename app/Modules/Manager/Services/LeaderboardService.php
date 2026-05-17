<?php

namespace App\Modules\Manager\Services;

use App\Models\Game;
use App\Models\ManagerStats;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
        'trophies_count',
    ];

    public const ALLOWED_MODES = [
        Game::MODE_CAREER,
        Game::MODE_CAREER_PRO,
    ];

    /**
     * Validate and normalize the sort column.
     */
    public function normalizeSort(string $sort): string
    {
        return in_array($sort, self::ALLOWED_SORTS) ? $sort : 'win_percentage';
    }

    /**
     * Validate and normalize the mode filter. Null means "All modes".
     */
    public function normalizeMode(?string $mode): ?string
    {
        return in_array($mode, self::ALLOWED_MODES, true) ? $mode : null;
    }

    /**
     * Get the paginated leaderboard rankings.
     */
    public function getRankings(string $sort, ?string $country, ?string $province, ?string $mode = null): LengthAwarePaginator
    {
        $query = ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'manager_stats.team_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->select('manager_stats.*', 'users.name', 'users.username', 'users.avatar', 'users.country', 'users.province', 'teams.name as team_name', 'teams.image as team_image');

        $this->applyModeFilter($query, $mode);

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
    public function getProvincesForCountry(string $country, ?string $mode = null): array
    {
        $query = ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('users.country', $country)
            ->whereNotNull('users.province')
            ->where('users.province', '!=', '');

        $this->applyModeFilter($query, $mode);

        return $query->distinct()
            ->orderBy('users.province')
            ->pluck('users.province')
            ->toArray();
    }

    /**
     * Get all countries with qualifying managers, localized.
     */
    public function getCountries(?string $mode = null): array
    {
        $locale = app()->getLocale();

        $query = ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->whereNotNull('users.country')
            ->where('users.country', '!=', '');

        $this->applyModeFilter($query, $mode);

        $countryCodes = $query->distinct()->pluck('users.country');

        return $countryCodes->mapWithKeys(function ($code) use ($locale) {
            $localized = Locale::getDisplayRegion('und_'.$code, $locale);

            return [$code => ($localized !== $code) ? $localized : $code];
        })->sort()->toArray();
    }

    /**
     * Get aggregate leaderboard stats (total qualifying managers, total matches).
     */
    public function getAggregateStats(?string $mode = null): array
    {
        $managersQuery = ManagerStats::query()
            ->where('matches_played', '>=', self::MIN_MATCHES);
        $matchesQuery = ManagerStats::query();

        $this->applyModeFilter($managersQuery, $mode);
        $this->applyModeFilter($matchesQuery, $mode);

        return [
            'totalManagers' => $managersQuery->count(),
            'totalMatches' => (int) $matchesQuery->sum('matches_played'),
        ];
    }

    /**
     * Get all club teams that have at least one qualifying manager, with manager counts.
     *
     * Team boards are Club Manager only — Pro Manager careers span multiple
     * teams and a short stint shouldn't appear on a club's "best managers" board.
     */
    public function getTeamsWithManagers(): Collection
    {
        return Team::query()
            ->join('manager_stats', 'teams.id', '=', 'manager_stats.team_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->where('manager_stats.game_mode', Game::MODE_CAREER)
            ->where('teams.type', 'club')
            ->where('teams.is_placeholder', false)
            ->whereNull('teams.parent_team_id')
            ->groupBy('teams.id', 'teams.name', 'teams.slug', 'teams.image', 'teams.transfermarkt_id', 'teams.type', 'teams.country')
            ->selectRaw('teams.id, teams.name, teams.slug, teams.image, teams.transfermarkt_id, teams.type, teams.country, count(distinct manager_stats.user_id) as managers_count')
            ->orderBy('teams.name')
            ->get();
    }

    /**
     * Get paginated leaderboard rankings filtered by team. Club Manager only.
     */
    public function getRankingsForTeam(string $teamId, string $sort): LengthAwarePaginator
    {
        return ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->leftJoin('teams', 'teams.id', '=', 'manager_stats.team_id')
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->where('manager_stats.team_id', $teamId)
            ->where('manager_stats.game_mode', Game::MODE_CAREER)
            ->select('manager_stats.*', 'users.name', 'users.username', 'users.avatar', 'users.country', 'users.province', 'teams.name as team_name', 'teams.image as team_image')
            ->orderByDesc("manager_stats.{$sort}")
            ->orderByDesc('manager_stats.matches_played')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Get aggregate stats for a specific team. Club Manager only.
     */
    public function getTeamAggregateStats(string $teamId): array
    {
        $query = ManagerStats::query()
            ->where('manager_stats.team_id', $teamId)
            ->where('manager_stats.game_mode', Game::MODE_CAREER);

        $totalManagers = (clone $query)
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->count();

        $totalMatches = (int) $query->sum('matches_played');

        return [
            'totalManagers' => $totalManagers,
            'totalMatches' => $totalMatches,
        ];
    }

    private function applyModeFilter(Builder $query, ?string $mode): void
    {
        if ($mode === null) {
            return;
        }

        $query->where('manager_stats.game_mode', $mode);
    }
}
