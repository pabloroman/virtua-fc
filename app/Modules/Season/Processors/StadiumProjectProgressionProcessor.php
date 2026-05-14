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
use App\Modules\Stadium\Enums\StadiumLoanStatus;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;

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
 * UEFA-upgrade lifecycle — one-step facility fit-out, no capacity change:
 *   pending      → completed     when completion_season == X+1
 *                                  (target_capacity stores the target UEFA
 *                                   level; written to rebuilt_uefa_level)
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
        // Cheap short-circuit: the vast majority of games have no stadium
        // projects or loans at any given time. Two cheap `exists()` queries
        // beat four targeted `get()`s every season for every game.
        $hasProjects = GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->whereIn('status', [
                StadiumProjectStatus::Pending->value,
                StadiumProjectStatus::InProgress->value,
            ])
            ->exists();

        $hasLoans = StadiumLoan::query()
            ->where('game_id', $game->id)
            ->where('status', StadiumLoanStatus::Active->value)
            ->exists();

        if (! $hasProjects && ! $hasLoans) {
            return $data;
        }

        $closingSeason = (int) $game->season;
        $nextSeason = $closingSeason + 1;

        if ($hasProjects) {
            $this->progressRebuilds($game, $closingSeason, $nextSeason);
            $this->progressStandExpansions($game, $nextSeason);
            $this->progressUefaUpgrades($game, $nextSeason);
        }

        if ($hasLoans) {
            $this->billActiveLoans($game);
        }

        return $data;
    }

    private function progressRebuilds(Game $game, int $closingSeason, int $nextSeason): void
    {
        $projects = GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->where('type', StadiumProjectType::Rebuild->value)
            ->whereIn('status', [
                StadiumProjectStatus::Pending->value,
                StadiumProjectStatus::InProgress->value,
            ])
            ->get();

        foreach ($projects as $project) {
            if (
                $project->status === StadiumProjectStatus::Pending
                && $project->committed_season === $closingSeason
            ) {
                $project->update(['status' => StadiumProjectStatus::InProgress]);
                continue;
            }

            if (
                $project->status === StadiumProjectStatus::InProgress
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
            ->where('type', StadiumProjectType::StandExpansion->value)
            ->where('status', StadiumProjectStatus::Pending->value)
            ->where('completion_season', $nextSeason)
            ->get();

        foreach ($projects as $project) {
            $this->completeStandExpansion($game, $project);
        }
    }

    private function progressUefaUpgrades(Game $game, int $nextSeason): void
    {
        $projects = GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->where('type', StadiumProjectType::UefaUpgrade->value)
            ->where('status', StadiumProjectStatus::Pending->value)
            ->where('completion_season', $nextSeason)
            ->get();

        foreach ($projects as $project) {
            $this->completeUefaUpgrade($game, $project);
        }
    }

    private function completeUefaUpgrade(Game $game, GameStadiumProject $project): void
    {
        $stadium = GameStadium::query()
            ->where('game_id', $project->game_id)
            ->where('team_id', $project->team_id)
            ->first();

        if (! $stadium) {
            return;
        }

        // target_capacity stores the target UEFA level for this project
        // type (commitUefaUpgrade stamps it as currentLevel + 1).
        $stadium->update(['rebuilt_uefa_level' => (int) $project->target_capacity]);

        $project->update([
            'status' => StadiumProjectStatus::Completed,
            'completion_date' => $game->current_date,
        ]);

        $this->notificationService->notifyStadiumProjectCompleted(
            $game,
            StadiumProjectType::UefaUpgrade,
            (int) $project->target_capacity,
        );
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
            'status' => StadiumProjectStatus::Completed,
            'completion_date' => $game->current_date,
        ]);

        $this->notificationService->notifyStadiumProjectCompleted(
            $game,
            StadiumProjectType::StandExpansion,
            $stadium->effective_capacity,
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
            'status' => StadiumProjectStatus::Completed,
            'completion_date' => $game->current_date,
        ]);

        $this->notificationService->notifyStadiumProjectCompleted(
            $game,
            StadiumProjectType::Rebuild,
            $stadium->effective_capacity,
        );
    }

    private function billActiveLoans(Game $game): void
    {
        $loans = StadiumLoan::query()
            ->where('game_id', $game->id)
            ->where('status', StadiumLoanStatus::Active->value)
            ->get();

        foreach ($loans as $loan) {
            $this->loanService->billAnnualPayment($loan, $game);
        }
    }
}
