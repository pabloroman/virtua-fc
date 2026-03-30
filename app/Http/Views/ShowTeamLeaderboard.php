<?php

namespace App\Http\Views;

use App\Models\Team;
use App\Modules\Manager\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShowTeamLeaderboard
{
    private const CACHE_TTL = 300;

    public function __construct(
        private LeaderboardService $leaderboardService,
    ) {}

    public function __invoke(Request $request, string $slug)
    {
        $team = Team::where('slug', $slug)
            ->where('type', 'club')
            ->firstOrFail();

        $sort = $this->leaderboardService->normalizeSort($request->query('sort', 'win_percentage'));
        $page = $request->query('page', 1);

        $cacheKey = "leaderboard:team:{$team->id}:{$sort}:{$page}";

        $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($team, $sort, $request) {
            $managers = $this->leaderboardService->getRankingsForTeam($team->id, $sort)
                ->appends($request->query());

            return [
                'managers' => $managers,
                ...$this->leaderboardService->getTeamAggregateStats($team->id),
            ];
        });

        return view('leaderboard.team', [
            ...$cached,
            'team' => $team,
            'currentSort' => $sort,
            'minMatches' => LeaderboardService::MIN_MATCHES,
        ]);
    }
}
