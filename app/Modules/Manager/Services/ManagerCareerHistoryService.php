<?php

namespace App\Modules\Manager\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\ManagerSeasonRecord;
use App\Models\ManagerTrophy;
use App\Models\Team;
use App\Modules\Season\Services\SeasonGoalService;
use Illuminate\Support\Collection;

/**
 * Assembles the pro-manager career history shown on /game/{id}/career.
 *
 * Returns one entry per managed season ordered chronologically: the
 * snapshot rows persisted by SnapshotManagerSeasonRecordProcessor at the
 * end of each season, plus a transient "in-progress" entry for the
 * current open season (built from the live Game so the user can see the
 * team and goal they're currently chasing).
 */
class ManagerCareerHistoryService
{
    public function __construct(
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    /**
     * @return Collection<int, array{
     *   season:string,
     *   season_label:string,
     *   team:?Team,
     *   competition:?Competition,
     *   season_goal:?string,
     *   season_goal_label:?string,
     *   final_position:?int,
     *   goal_achieved:?bool,
     *   goal_grade:?string,
     *   end_reason:?string,
     *   in_progress:bool,
     * }>
     */
    public function historyFor(Game $game, int $userId): Collection
    {
        $records = ManagerSeasonRecord::with(['team', 'competition'])
            ->where('game_id', $game->id)
            ->where('user_id', $userId)
            ->orderBy('season')
            ->get();

        $entries = $records->map(fn (ManagerSeasonRecord $record) => [
            'season' => $record->season,
            'season_label' => Game::formatSeason($record->season),
            'team' => $record->team,
            'competition' => $record->competition,
            'season_goal' => $record->season_goal,
            'season_goal_label' => $record->season_goal_label,
            'final_position' => $record->final_position,
            'goal_achieved' => $record->goal_achieved,
            'goal_grade' => $record->goal_grade,
            'end_reason' => $record->end_reason,
            'in_progress' => false,
        ]);

        $currentSeasonAlreadyRecorded = $records->contains(
            fn (ManagerSeasonRecord $record) => $record->season === $game->season,
        );

        if (!$currentSeasonAlreadyRecorded) {
            $entries->push($this->buildInProgressEntry($game));
        }

        return $entries->values();
    }

    /**
     * Returns trophies won during this game, grouped by manager stint. A
     * stint is a contiguous run of seasons at the same team — if the
     * manager left a club and later returned, the two spells are listed
     * as separate stints with their own trophy lists.
     *
     * Only stints that won at least one trophy are returned. Stints are
     * ordered chronologically (earliest first) to match the existing
     * career timeline on the page.
     *
     * @return Collection<int, array{
     *   team:?Team,
     *   season_start:string,
     *   season_end:string,
     *   season_range_label:string,
     *   total:int,
     *   trophies:list<array{competition_id:string,competition_name:string,trophy_type:string,count:int,seasons:list<string>}>,
     * }>
     */
    public function trophiesByStint(Game $game, int $userId): Collection
    {
        $records = ManagerSeasonRecord::with('team')
            ->where('game_id', $game->id)
            ->where('user_id', $userId)
            ->orderBy('season')
            ->get();

        $timeline = $records->map(fn (ManagerSeasonRecord $record) => [
            'season' => $record->season,
            'team_id' => $record->team_id,
            'team' => $record->team,
        ])->all();

        $currentSeasonAlreadyRecorded = $records->contains(
            fn (ManagerSeasonRecord $record) => $record->season === $game->season,
        );

        if (!$currentSeasonAlreadyRecorded) {
            $timeline[] = [
                'season' => $game->season,
                'team_id' => $game->team_id,
                'team' => $game->team,
            ];
        }

        if (empty($timeline)) {
            return collect();
        }

        // Walk the timeline and start a fresh stint whenever the team
        // changes. This is how non-consecutive spells at the same club
        // end up as separate groups.
        $stints = [];
        $current = null;
        foreach ($timeline as $entry) {
            if ($current === null || $current['team_id'] !== $entry['team_id']) {
                if ($current !== null) {
                    $stints[] = $current;
                }
                $current = [
                    'team_id' => $entry['team_id'],
                    'team' => $entry['team'],
                    'seasons' => [],
                ];
            }
            $current['seasons'][] = $entry['season'];
        }
        if ($current !== null) {
            $stints[] = $current;
        }

        // Single query for every trophy the user has won in this game;
        // we slice it per-stint in PHP to avoid N+1.
        $allTrophies = ManagerTrophy::with('competition')
            ->where('user_id', $userId)
            ->where('game_id', $game->id)
            ->get();

        $typePriority = [
            'league' => 0,
            'cup' => 1,
            'european' => 2,
            'supercup' => 3,
        ];

        $result = [];
        foreach ($stints as $stint) {
            $stintTrophies = $allTrophies->filter(
                fn (ManagerTrophy $trophy) => $trophy->team_id === $stint['team_id']
                    && in_array($trophy->season, $stint['seasons'], true),
            );

            if ($stintTrophies->isEmpty()) {
                continue;
            }

            $grouped = [];
            foreach ($stintTrophies as $trophy) {
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
                $entry['seasons'] = array_map(
                    fn (string $season) => Game::formatSeason($season),
                    $entry['seasons'],
                );
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

            $firstSeason = $stint['seasons'][0];
            $lastSeason = end($stint['seasons']);
            $firstLabel = Game::formatSeason($firstSeason);
            $lastLabel = Game::formatSeason($lastSeason);

            $result[] = [
                'team' => $stint['team'],
                'season_start' => $firstSeason,
                'season_end' => $lastSeason,
                'season_range_label' => $firstSeason === $lastSeason
                    ? $firstLabel
                    : $firstLabel . ' – ' . $lastLabel,
                'total' => $stintTrophies->count(),
                'trophies' => $list,
            ];
        }

        return collect($result);
    }

    /**
     * @return array{
     *   season:string,
     *   season_label:string,
     *   team:?Team,
     *   competition:?Competition,
     *   season_goal:?string,
     *   season_goal_label:?string,
     *   final_position:?int,
     *   goal_achieved:?bool,
     *   goal_grade:?string,
     *   end_reason:?string,
     *   in_progress:bool,
     * }
     */
    private function buildInProgressEntry(Game $game): array
    {
        $team = $game->team;
        $competition = $game->competition;

        $goalLabel = null;
        if ($game->season_goal && $competition) {
            $goalLabel = __($this->seasonGoalService->getGoalLabel($game->season_goal, $competition));
        }

        return [
            'season' => $game->season,
            'season_label' => $game->formatted_season,
            'team' => $team,
            'competition' => $competition,
            'season_goal' => $game->season_goal,
            'season_goal_label' => $goalLabel,
            'final_position' => null,
            'goal_achieved' => null,
            'goal_grade' => null,
            'end_reason' => null,
            'in_progress' => true,
        ];
    }
}
