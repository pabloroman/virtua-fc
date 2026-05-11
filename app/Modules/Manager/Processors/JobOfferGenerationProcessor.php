<?php

namespace App\Modules\Manager\Processors;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonGoalService;

/**
 * Generates pro-manager end-of-season job offers based on the manager's
 * performance grade. No-op for non-pro games and for any pro game whose
 * manager has somehow already been processed (resume-after-crash safety).
 *
 * Priority 18: after TrophyRecordingProcessor (10) and LeaderboardStats (15),
 * before SeasonArchiveProcessor (25) so league standings are still intact
 * when we read the manager's final position.
 */
class JobOfferGenerationProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly JobOfferService $jobOfferService,
        private readonly SeasonGoalService $seasonGoalService,
        private readonly PromotionRelegationFactory $promotionRelegationFactory,
        private readonly NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 18;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!$game->isProManagerMode()) {
            return $data;
        }

        // Idempotency: skip if this season already produced offers for this game.
        $alreadyOffered = \App\Models\ManagerJobOffer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->exists();

        if ($alreadyOffered) {
            return $data;
        }

        $grade = $this->resolveGrade($game);

        $offers = $this->jobOfferService->generateEndOfSeasonOffers($game, $grade);

        if ($offers->isNotEmpty()) {
            // refresh() so the fired_at_season_end flag (just persisted by the
            // service for disasters) is visible to the notifier.
            $this->notificationService->notifyJobOfferReceived(
                $game->refresh(),
                $offers->count(),
                $grade === 'disaster',
            );
        }

        return $data;
    }

    /**
     * Determine the manager's performance grade for the season just closed.
     * Mirrors the calculation in SeasonSummaryService::buildSeasonSummary().
     */
    private function resolveGrade(Game $game): string
    {
        $playerStanding = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        $promoted = $this->wasPromoted($game);

        $evaluation = $this->seasonGoalService->evaluatePerformance(
            $game,
            $playerStanding->position ?? 20,
            $promoted,
        );

        return $evaluation['grade'] ?? 'met';
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
