<?php

namespace App\Modules\Manager\Services;

use App\Models\Game;
use App\Models\ManagerStats;
use App\Models\ManagerTrophy;

/**
 * Assembles the compact "career so far" strip shown on the Reputation page.
 * All facts are derived from existing per-game records so the strip is
 * meaningful from the first match onward. Cross-season history (highest
 * league finish, promotions) is deferred to a dedicated history surface.
 */
class CareerSummaryService
{
    /**
     * @return array{
     *   seasons_managed:int,
     *   matches_played:int,
     *   trophies:int,
     *   starting_tier:string,
     * }
     */
    public function build(Game $game, int $userId): array
    {
        $stats = ManagerStats::where('user_id', $userId)
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $trophies = ManagerTrophy::where('user_id', $userId)
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->count();

        $seasonsCompleted = (int) ($stats?->seasons_completed ?? 0);

        return [
            'seasons_managed' => $seasonsCompleted + 1,
            'matches_played' => (int) ($stats?->matches_played ?? 0),
            'trophies' => $trophies,
            'starting_tier' => $game->team?->clubProfile?->reputation_level ?? 'local',
        ];
    }

    /**
     * Groups the user's trophies in this game by competition and returns a
     * flat, pre-sorted list suitable for rendering a "Trophy cabinet" widget.
     *
     * Ordering: league titles first, then domestic cups, then European, then
     * supercups — matching the perceived prestige tiers. Within a type,
     * competitions with more titles rank higher; ties break alphabetically.
     *
     * @return list<array{competition_id:string,competition_name:string,trophy_type:string,count:int,seasons:list<string>}>
     */
    public function buildTrophyCabinet(Game $game, int $userId): array
    {
        $trophies = ManagerTrophy::with('competition')
            ->where('user_id', $userId)
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        if ($trophies->isEmpty()) {
            return [];
        }

        $typePriority = [
            'league' => 0,
            'cup' => 1,
            'european' => 2,
            'supercup' => 3,
        ];

        $grouped = [];
        foreach ($trophies as $trophy) {
            $key = $trophy->competition_id;
            $grouped[$key] ??= [
                'competition_id' => $trophy->competition_id,
                'competition_name' => $trophy->competition?->name ?? $trophy->competition_id,
                'trophy_type' => $trophy->trophy_type,
                'count' => 0,
                'seasons' => [],
            ];

            $grouped[$key]['count']++;
            $grouped[$key]['seasons'][] = (string) $trophy->season;
        }

        foreach ($grouped as &$entry) {
            sort($entry['seasons']);
        }
        unset($entry);

        $list = array_values($grouped);
        usort($list, function (array $a, array $b) use ($typePriority) {
            $priorityA = $typePriority[$a['trophy_type']] ?? 99;
            $priorityB = $typePriority[$b['trophy_type']] ?? 99;
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }
            return strcmp($a['competition_name'], $b['competition_name']);
        });

        return $list;
    }
}
