<?php

namespace App\Modules\Report\Services;

use App\Models\GameMatch;
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
     * Build MVP rankings: top MVPs and the user's team MVP leader.
     *
     * @return array{Collection, ?object} [$topMvps, $teamMvpLeader]
     */
    public function getMvpRankings(string $gameId, ?string $competitionId, string $teamId, int $limit = 5): array
    {
        $mvpCounts = GameMatch::mvpCountsByPlayer($gameId, $competitionId);

        if ($mvpCounts->isEmpty()) {
            return [collect(), null, $mvpCounts];
        }

        $players = GamePlayer::with(['player', 'team'])
            ->whereIn('id', $mvpCounts->keys()->all())
            ->get()
            ->keyBy('id');

        $ranked = $mvpCounts
            ->map(fn ($count, $playerId) => (object) [
                'gamePlayer' => $players->get($playerId),
                'count' => $count,
            ])
            ->filter(fn ($item) => $item->gamePlayer !== null)
            ->sortByDesc('count')
            ->values();

        $topMvps = $ranked->take($limit);
        $teamMvpLeader = $ranked->first(fn ($item) => $item->gamePlayer->team_id === $teamId);

        return [$topMvps, $teamMvpLeader, $mvpCounts];
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
