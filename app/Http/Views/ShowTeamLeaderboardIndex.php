<?php

namespace App\Http\Views;

use App\Modules\Manager\Services\LeaderboardService;
use Illuminate\Support\Facades\Cache;
use Locale;

class ShowTeamLeaderboardIndex
{
    private const CACHE_TTL = 300;

    public function __construct(
        private LeaderboardService $leaderboardService,
    ) {}

    public function __invoke()
    {
        $data = Cache::remember('leaderboard:teams-index', self::CACHE_TTL, function () {
            $teams = $this->leaderboardService->getTeamsWithManagers();
            $locale = app()->getLocale();

            $grouped = $teams->groupBy('country')->map(function ($countryTeams, $code) use ($locale) {
                $localized = Locale::getDisplayRegion('und_'.strtoupper($code), $locale);

                return [
                    'name' => ($localized !== $code) ? $localized : $code,
                    'teams' => $countryTeams->sortBy('name')->values(),
                ];
            })->sortBy('name')->values();

            return ['teamsByCountry' => $grouped];
        });

        return view('leaderboard.teams-index', $data);
    }
}
