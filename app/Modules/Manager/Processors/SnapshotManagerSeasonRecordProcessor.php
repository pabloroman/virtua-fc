<?php

namespace App\Modules\Manager\Processors;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\ManagerSeasonRecord;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
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
 * the final standings, $game->season_goal, fired_at_season_end, and
 * pending_team_switch all still describe the season we're closing.
 *
 * Idempotent on (game_id, user_id, season). Skips non-pro-manager games.
 */
class SnapshotManagerSeasonRecordProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SeasonGoalService $seasonGoalService,
        private readonly PromotionRelegationFactory $promotionRelegationFactory,
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

        $promoted = $this->wasPromoted($game);
        $evaluation = $this->seasonGoalService->evaluatePerformance(
            $game,
            $finalPosition ?? 20,
            $promoted,
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
        if ($game->fired_at_season_end) {
            return ManagerSeasonRecord::END_REASON_FIRED;
        }

        if ($game->pending_team_switch) {
            return ManagerSeasonRecord::END_REASON_LEFT_VOLUNTARILY;
        }

        return ManagerSeasonRecord::END_REASON_STILL_ACTIVE;
    }

    private function wasPromoted(Game $game): bool
    {
        $competition = Competition::find($game->competition_id);
        if (!$competition) {
            return false;
        }

        $rule = $this->promotionRelegationFactory->forCompetition($competition->id);
        if (!$rule) {
            return false;
        }

        try {
            $promoted = $rule->getPromotedTeams($game);
        } catch (\Throwable) {
            return false;
        }

        foreach ($promoted as $entry) {
            if (($entry['teamId'] ?? null) === $game->team_id) {
                return true;
            }
        }

        return false;
    }
}
