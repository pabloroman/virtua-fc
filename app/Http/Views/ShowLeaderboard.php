<?php

namespace App\Http\Views;

use App\Modules\Manager\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShowLeaderboard
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private LeaderboardService $leaderboardService,
    ) {}

    public function __invoke(Request $request)
    {
        $country = $request->query('country');
        $province = $request->query('province');
        $sort = $this->leaderboardService->normalizeSort($request->query('sort', 'win_percentage'));
        $mode = $this->leaderboardService->normalizeMode($request->query('mode'));
        $page = $request->query('page', 1);

        $cacheKey = "leaderboard:{$sort}:{$country}:{$province}:{$mode}:{$page}";

        $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($country, $province, $sort, $mode, $request) {
            $managers = $this->leaderboardService->getRankings($sort, $country, $province, $mode)
                ->appends($request->query());

            $provinces = $country
                ? $this->leaderboardService->getProvincesForCountry($country, $mode)
                : [];

            return [
                'managers' => $managers,
                'countries' => $this->leaderboardService->getCountries($mode),
                'provinces' => $provinces,
                ...$this->leaderboardService->getAggregateStats($mode),
            ];
        });

        return view('leaderboard', [
            ...$cached,
            'selectedCountry' => $country,
            'selectedProvince' => $province,
            'currentSort' => $sort,
            'selectedMode' => $mode,
            'minMatches' => LeaderboardService::MIN_MATCHES,
        ]);
    }
}
