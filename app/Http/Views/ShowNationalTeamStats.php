<?php

namespace App\Http\Views;

use App\Models\Team;
use App\Modules\Manager\Services\NationalTeamStatsService;
use Illuminate\Support\Facades\Cache;

class ShowNationalTeamStats
{
    private const CACHE_TTL = 300;

    public function __construct(
        private NationalTeamStatsService $service,
    ) {}

    public function __invoke(string $slug)
    {
        $team = Team::where('slug', $slug)
            ->where('type', 'national')
            ->firstOrFail();

        $data = Cache::remember("national-team-stats:team:{$team->id}", self::CACHE_TTL, function () use ($team) {
            return [
                'stats' => $this->service->getTeamStats($team->id),
                'resultDistribution' => $this->service->getResultDistribution($team->id),
                'playerFrequency' => $this->service->getPlayerFrequency($team->id),
            ];
        });

        return view('leaderboard.national-team', [
            'team' => $team,
            ...$data,
        ]);
    }
}
