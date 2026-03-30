<?php

namespace App\Http\Views;

use App\Modules\Manager\Services\TournamentLeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ShowTournamentLeaderboard
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private TournamentLeaderboardService $service,
    ) {}

    public function __invoke(Request $request)
    {
        $country = $request->query('country');
        $team = $request->query('team');
        $sort = $this->service->normalizeSort($request->query('sort', 'tournaments_won'));
        $page = max(1, (int) $request->query('page', 1));

        if ($team && !Str::isUuid($team)) {
            $team = null;
        }

        $cacheKey = "leaderboard:tournament:{$sort}:{$country}:{$team}:{$page}";

        $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($country, $team, $sort, $request) {
            $rankings = $this->service->getRankings($sort, $country, $team)
                ->appends($request->query());

            return [
                'rankings' => $rankings,
                'countries' => $this->service->getCountries(),
                'teamsPlayed' => $this->service->getTeamsPlayed(),
                ...$this->service->getAggregateStats(),
            ];
        });

        return view('leaderboard.tournament', [
            ...$cached,
            'selectedCountry' => $country,
            'selectedTeam' => $team,
            'currentSort' => $sort,
        ]);
    }
}
