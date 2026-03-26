<?php

namespace App\Http\Views;

use App\Modules\Manager\Services\LeaderboardService;
use Illuminate\Support\Facades\Cache;

class ShowTeamLeaderboardIndex
{
    private const CACHE_TTL = 300;

    public function __construct(
        private LeaderboardService $leaderboardService,
    ) {}

    public function __invoke()
    {
        $teams = Cache::remember('leaderboard:teams-index', self::CACHE_TTL, function () {
            return $this->leaderboardService->getTeamsWithManagers();
        });

        return view('leaderboard.teams-index', [
            'teams' => $teams,
        ]);
    }
}
