<?php

namespace App\Modules\Manager\Services;

use App\Models\ManagerStats;

/**
 * Merges career stats from an external `manager_stats` source (the OLD beta
 * server) into NEW orphan rows — rows where `game_id IS NULL` because the
 * user deleted the career on NEW. The OLD row and the NEW orphan represent
 * the user's *separate* runs at the same team (the migration manifest bug
 * sometimes left collapsed survivors on NEW that the user then re-played and
 * deleted), so the merge is additive for cumulative columns and "max" for
 * peak/streak columns. Win percentage is recomputed from the summed totals.
 *
 * Matching is by (`user_id`, `team_id`). Anything ambiguous on either side
 * is skipped and reported — the operator resolves manually.
 */
class OrphanManagerStatsMerger
{
    /**
     * @param iterable<array<string,mixed>> $oldRows
     * @return array{
     *     merged: list<array{user_id:int,team_id:string,before:array<string,int|float>,after:array<string,int|float>}>,
     *     no_old_match: list<array{user_id:int,team_id:string}>,
     *     ambiguous_old: list<array{user_id:int,team_id:string,count:int}>,
     *     ambiguous_orphan: list<array{user_id:int,team_id:string,count:int}>,
     * }
     */
    public function merge(iterable $oldRows, bool $dryRun = false): array
    {
        $oldByKey = $this->indexByUserTeam($oldRows);

        $orphansByKey = [];
        $orphans = ManagerStats::whereNull('game_id')
            ->whereNotNull('team_id')
            ->get();
        foreach ($orphans as $orphan) {
            $orphansByKey[$this->key($orphan->user_id, $orphan->team_id)][] = $orphan;
        }

        $summary = [
            'merged' => [],
            'no_old_match' => [],
            'ambiguous_old' => [],
            'ambiguous_orphan' => [],
        ];

        foreach ($orphansByKey as $key => $matching) {
            [$userId, $teamId] = $this->splitKey($key);

            if (count($matching) > 1) {
                $summary['ambiguous_orphan'][] = [
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'count' => count($matching),
                ];
                continue;
            }

            $orphan = $matching[0];
            $oldMatches = $oldByKey[$key] ?? [];

            if (count($oldMatches) === 0) {
                $summary['no_old_match'][] = [
                    'user_id' => $userId,
                    'team_id' => $teamId,
                ];
                continue;
            }

            if (count($oldMatches) > 1) {
                $summary['ambiguous_old'][] = [
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'count' => count($oldMatches),
                ];
                continue;
            }

            $before = $this->snapshot($orphan);
            $this->applyMerge($orphan, $oldMatches[0]);
            $after = $this->snapshot($orphan);

            if (! $dryRun) {
                $orphan->save();
            }

            $summary['merged'][] = [
                'user_id' => $userId,
                'team_id' => $teamId,
                'before' => $before,
                'after' => $after,
            ];
        }

        return $summary;
    }

    /**
     * @param iterable<array<string,mixed>> $rows
     * @return array<string, list<array<string,mixed>>>
     */
    private function indexByUserTeam(iterable $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $userId = $row['user_id'] ?? null;
            $teamId = $row['team_id'] ?? null;
            if ($userId === null || $teamId === null) {
                continue;
            }
            $byKey[$this->key((int) $userId, (string) $teamId)][] = $row;
        }

        return $byKey;
    }

    private function applyMerge(ManagerStats $orphan, array $old): void
    {
        $orphan->matches_played += (int) ($old['matches_played'] ?? 0);
        $orphan->matches_won += (int) ($old['matches_won'] ?? 0);
        $orphan->matches_drawn += (int) ($old['matches_drawn'] ?? 0);
        $orphan->matches_lost += (int) ($old['matches_lost'] ?? 0);
        $orphan->seasons_completed += (int) ($old['seasons_completed'] ?? 0);

        $orphan->longest_unbeaten_streak = max(
            (int) $orphan->longest_unbeaten_streak,
            (int) ($old['longest_unbeaten_streak'] ?? 0),
        );
        $orphan->current_unbeaten_streak = max(
            (int) $orphan->current_unbeaten_streak,
            (int) ($old['current_unbeaten_streak'] ?? 0),
        );

        $orphan->recalculateWinPercentage();
    }

    /**
     * @return array<string,int|float>
     */
    private function snapshot(ManagerStats $stats): array
    {
        return [
            'matches_played' => (int) $stats->matches_played,
            'matches_won' => (int) $stats->matches_won,
            'matches_drawn' => (int) $stats->matches_drawn,
            'matches_lost' => (int) $stats->matches_lost,
            'win_percentage' => (float) $stats->win_percentage,
            'current_unbeaten_streak' => (int) $stats->current_unbeaten_streak,
            'longest_unbeaten_streak' => (int) $stats->longest_unbeaten_streak,
            'seasons_completed' => (int) $stats->seasons_completed,
        ];
    }

    private function key(int $userId, string $teamId): string
    {
        return $userId . '|' . $teamId;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function splitKey(string $key): array
    {
        [$userId, $teamId] = explode('|', $key, 2);

        return [(int) $userId, $teamId];
    }
}
