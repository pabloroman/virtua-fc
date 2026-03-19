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
        $page = $request->query('page', 1);

        $cacheKey = "leaderboard:{$sort}:{$country}:{$province}:{$page}";

        $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($country, $province, $sort, $request) {
            $managers = $this->leaderboardService->getRankings($sort, $country, $province)
                ->appends($request->query());

            $provinces = $country
                ? $this->leaderboardService->getProvincesForCountry($country)
                : [];

            return [
                'managers' => $managers,
                'countries' => $this->leaderboardService->getCountries(),
                'provinces' => $provinces,
                ...$this->leaderboardService->getAggregateStats(),
            ];
        });

        return view('leaderboard', [
            ...$cached,
            'selectedCountry' => $country,
            'selectedProvince' => $province,
            'currentSort' => $sort,
            'minMatches' => LeaderboardService::MIN_MATCHES,
        ]);
    }
}
