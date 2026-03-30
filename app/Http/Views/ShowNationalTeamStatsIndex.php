<?php

namespace App\Http\Views;

use App\Modules\Manager\Services\NationalTeamStatsService;
use Illuminate\Support\Facades\Cache;

class ShowNationalTeamStatsIndex
{
    private const CACHE_TTL = 300;

    public function __construct(
        private NationalTeamStatsService $service,
    ) {}

    public function __invoke()
    {
        $data = Cache::remember('national-team-stats:index', self::CACHE_TTL, function () {
            return [
                'teams' => $this->service->getTeamsWithTournamentCounts(),
                ...$this->service->getIndexAggregateStats(),
            ];
        });

        return view('leaderboard.national-teams-index', $data);
    }
}
