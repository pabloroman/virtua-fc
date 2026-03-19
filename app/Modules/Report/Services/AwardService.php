<?php

namespace App\Modules\Report\Services;

use App\Models\GamePlayer;
use Illuminate\Support\Collection;

class AwardService
{
    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTopScorers(string $gameId, Collection|array|null $teamIds = null, int $limit = 5): Collection
    {
        return GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderBy('appearances')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTopAssisters(string $gameId, Collection|array|null $teamIds = null, int $limit = 5): Collection
    {
        return GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->where('assists', '>', 0)
            ->orderByDesc('assists')
            ->orderByDesc('goals')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTopGoalkeepers(string $gameId, Collection|array|null $teamIds = null, int $minAppearances = 3, int $limit = 5): Collection
    {
        return GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->where('position', 'Goalkeeper')
            ->where('appearances', '>=', $minAppearances)
            ->get()
            ->sortBy([
                ['clean_sheets', 'desc'],
                [fn ($gk) => $gk->appearances > 0 ? $gk->goals_conceded / $gk->appearances : 999, 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    public function getTeamSquadStats(string $gameId, string $teamId): Collection
    {
        return GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->orderByDesc('appearances')
            ->get();
    }
}
