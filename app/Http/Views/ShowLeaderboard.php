<?php

namespace App\Http\Views;

use App\Models\ManagerStats;
use App\Support\ProfileCountries;
use Illuminate\Http\Request;

class ShowLeaderboard
{
    private const MIN_MATCHES = 10;
    private const PER_PAGE = 50;

    public function __invoke(Request $request)
    {
        $country = $request->query('country');
        $province = $request->query('province');
        $sort = $request->query('sort', 'win_percentage');

        $allowedSorts = ['win_percentage', 'longest_unbeaten_streak', 'matches_played', 'seasons_completed'];
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'win_percentage';
        }

        $query = ManagerStats::query()
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('users.is_profile_public', true)
            ->where('manager_stats.matches_played', '>=', self::MIN_MATCHES)
            ->select('manager_stats.*', 'users.name', 'users.username', 'users.avatar', 'users.country', 'users.province');

        if ($country) {
            $query->where('users.country', $country);
        }

        if ($province && $country) {
            $query->where('users.province', $province);
        }

        $managers = $query->orderByDesc("manager_stats.{$sort}")
            ->orderByDesc('manager_stats.matches_played')
            ->paginate(self::PER_PAGE)
            ->appends($request->query());

        // Get distinct provinces for the selected country
        $provinces = [];
        if ($country) {
            $provinces = ManagerStats::query()
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

        $countries = ProfileCountries::all();

        // Summary stats
        $totalManagers = ManagerStats::where('matches_played', '>=', self::MIN_MATCHES)
            ->join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('users.is_profile_public', true)
            ->count();

        $totalMatches = ManagerStats::join('users', 'users.id', '=', 'manager_stats.user_id')
            ->where('users.is_profile_public', true)
            ->sum('matches_played');

        return view('leaderboard', [
            'managers' => $managers,
            'countries' => $countries,
            'provinces' => $provinces,
            'selectedCountry' => $country,
            'selectedProvince' => $province,
            'currentSort' => $sort,
            'totalManagers' => $totalManagers,
            'totalMatches' => (int) $totalMatches,
            'minMatches' => self::MIN_MATCHES,
        ]);
    }
}
