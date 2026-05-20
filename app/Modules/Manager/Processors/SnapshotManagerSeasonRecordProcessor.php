<?php

namespace App\Modules\Manager\Processors;

use App\Models\Game;
use App\Models\GameStanding;
use App\Models\ManagerSeasonRecord;
use App\Modules\Competition\Promotions\PromotionRelegationQuery;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonGoalService;

/**
 * Snapshot the just-finished season into manager_season_records so the
 * pro-manager career history page can show one row per managed season
 * (team, league, goal, achievement) even after $game->season_goal is
 * reset by the setup pipeline and the GameStanding rows are wiped.
 *
 * Runs in the closing pipeline at priority 20: after TrophyRecording (10)
 * and LeaderboardStats (15), before SeasonArchive (25) — at this point
 * the final standings, $game->season_goal, the season's POST_FIRING offer
 * rows (read via Game::wasFiredThisSeason), and pending_team_switch all
 * still describe the season we're closing.
 *
 * Idempotent on (game_id, user_id, season). Skips non-pro-manager games.
 */
class SnapshotManagerSeasonRecordProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SeasonGoalService $seasonGoalService,
        private readonly PromotionRelegationQuery $promotionRelegationQuery,
    ) {}

    public function priority(): int
    {
        return 20;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!$game->isProManagerMode()) {
            return $data;
        }

        $standing = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        $finalPosition = $standing?->position;

        $evaluation = $this->seasonGoalService->evaluatePerformance(
            $game,
            $finalPosition ?? 20,
            $this->promotionRelegationQuery->wasTeamPromoted($game),
        );

        ManagerSeasonRecord::updateOrCreate(
            [
                'game_id' => $game->id,
                'user_id' => $game->user_id,
                'season' => $game->season,
            ],
            [
                'team_id' => $game->team_id,
                'competition_id' => $game->competition_id,
                'season_goal' => $game->season_goal,
                'season_goal_label' => $evaluation['goalLabel'] ?? null,
                'final_position' => $finalPosition,
                'goal_achieved' => $evaluation['achieved'] ?? null,
                'goal_grade' => $evaluation['grade'] ?? null,
                'end_reason' => $this->resolveEndReason($game),
                'recorded_at' => now(),
            ]
        );

        return $data;
    }

    private function resolveEndReason(Game $game): string
    {
        if ($game->wasFiredThisSeason()) {
            return ManagerSeasonRecord::END_REASON_FIRED;
        }

        if ($game->pending_team_switch) {
            return ManagerSeasonRecord::END_REASON_LEFT_VOLUNTARILY;
        }

        return ManagerSeasonRecord::END_REASON_STILL_ACTIVE;
    }
}
