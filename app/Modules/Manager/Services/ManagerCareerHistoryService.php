<?php

namespace App\Modules\Manager\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\ManagerSeasonRecord;
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
