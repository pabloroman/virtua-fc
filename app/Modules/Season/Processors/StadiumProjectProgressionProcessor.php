<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\GameStadium;
use App\Models\GameStadiumProject;
use App\Models\StadiumLoan;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

/**
 * Progresses rebuild and stand-expansion projects across the season
 * boundary and bills the annual instalment on every active stadium loan.
 *
 * Rebuild lifecycle (within the closing of season X, $game->season == X):
 *   pending      → in_progress   when committed_season == X
 *                                  (next season X+1 is the construction year)
 *   in_progress  → completed     when completion_season == X+1
 *                                  (capacity goes live in X+1; rebuilt_capacity
 *                                   is set on the stadium and any supplementary
 *                                   seats are folded in)
 *
 * Stand-expansion lifecycle — one-step (no mid-construction disruption):
 *   pending      → completed     when completion_season == X+1
 *                                  (target_capacity added to rebuilt_capacity;
 *                                   supplementary stands left untouched)
 *
 * Runs at priority 65 — immediately after SeasonSettlementProcessor (60),
 * which handles the existing single-season budget-loan repayment. Stadium-
 * loan instalments are billed here so that BudgetProjectionProcessor in the
 * setup pipeline sees an up-to-date remaining_principal when projecting next
 * season's debt service.
 */
class StadiumProjectProgressionProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly StadiumLoanService $loanService,
        private readonly NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 65;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $closingSeason = (int) $game->season;
        $nextSeason = $closingSeason + 1;

        $this->progressRebuilds($game, $closingSeason, $nextSeason);
        $this->progressStandExpansions($game, $nextSeason);
        $this->billActiveLoans($game);

        return $data;
    }

    private function progressRebuilds(Game $game, int $closingSeason, int $nextSeason): void
    {
        $projects = GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->where('type', GameStadiumProject::TYPE_REBUILD)
            ->whereIn('status', [
                GameStadiumProject::STATUS_PENDING,
                GameStadiumProject::STATUS_IN_PROGRESS,
            ])
            ->get();

        foreach ($projects as $project) {
            if (
                $project->status === GameStadiumProject::STATUS_PENDING
                && $project->committed_season === $closingSeason
            ) {
                $project->update(['status' => GameStadiumProject::STATUS_IN_PROGRESS]);
                continue;
            }

            if (
                $project->status === GameStadiumProject::STATUS_IN_PROGRESS
                && $project->completion_season === $nextSeason
            ) {
                $this->completeRebuild($game, $project);
            }
        }
    }

    private function progressStandExpansions(Game $game, int $nextSeason): void
    {
        $projects = GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->where('type', GameStadiumProject::TYPE_STAND_EXPANSION)
            ->where('status', GameStadiumProject::STATUS_PENDING)
            ->where('completion_season', $nextSeason)
            ->get();

        foreach ($projects as $project) {
            $this->completeStandExpansion($game, $project);
        }
    }

    private function completeStandExpansion(Game $game, GameStadiumProject $project): void
    {
        $stadium = GameStadium::query()
            ->where('game_id', $project->game_id)
            ->where('team_id', $project->team_id)
            ->first();

        if (! $stadium) {
            return;
        }

        // Stand expansion adds permanent seats on top of the existing
        // (rebuilt or base) capacity. rebuilt_capacity is the single
        // source of truth for permanent capacity after the first
        // change — initialise it from base_capacity on the first
        // expansion so subsequent expansions accumulate cleanly.
        $currentPermanent = $stadium->rebuilt_capacity ?? $stadium->base_capacity;
        $stadium->update([
            'rebuilt_capacity' => $currentPermanent + $project->target_capacity,
        ]);

        $project->update([
            'status' => GameStadiumProject::STATUS_COMPLETED,
            'completion_date' => $game->current_date,
        ]);

        $this->notificationService->notifyStadiumProjectCompleted(
            $game,
            GameStadiumProject::TYPE_STAND_EXPANSION,
            $stadium->fresh()->effective_capacity,
        );
    }

    private function completeRebuild(Game $game, GameStadiumProject $project): void
    {
        $stadium = GameStadium::query()
            ->where('game_id', $project->game_id)
            ->where('team_id', $project->team_id)
            ->first();

        if (! $stadium) {
            return;
        }

        // Fold existing supplementary stands into the rebuilt capacity so
        // the user keeps their seats and gains back the full 5,000-seat
        // headroom for future supletorias.
        $newBase = $project->target_capacity + $stadium->supplementary_seats;
        $stadium->update([
            'rebuilt_capacity' => $newBase,
            'supplementary_seats' => 0,
        ]);

        $project->update([
            'status' => GameStadiumProject::STATUS_COMPLETED,
            'completion_date' => $game->current_date,
        ]);

        $this->notificationService->notifyStadiumProjectCompleted(
            $game,
            GameStadiumProject::TYPE_REBUILD,
            $stadium->fresh()->effective_capacity,
        );
    }

    private function billActiveLoans(Game $game): void
    {
        $loans = StadiumLoan::query()
            ->where('game_id', $game->id)
            ->where('status', StadiumLoan::STATUS_ACTIVE)
            ->get();

        foreach ($loans as $loan) {
            $this->loanService->billAnnualPayment($loan, $game);
        }
    }
}
