<?php

namespace App\Modules\Finance\Services;

use App\Models\ClubProfile;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameStadium;
use App\Models\GameStadiumProject;
use App\Models\StadiumLoan;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Modules\Notification\Services\NotificationService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StadiumUpgradeService
{
    /**
     * Reputation tiers (and above) that can commission a full stadium
     * rebuild. Supplementary stands have no reputation gate.
     */
    private const REBUILD_MIN_REPUTATION = ClubProfile::REPUTATION_MODEST;

    public function __construct(
        private readonly StadiumLoanService $loanService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Resolve the per-game stadium row, falling back to a freshly seeded
     * row if a long-running save predates the backfill migration.
     */
    public function stadiumFor(Game $game): GameStadium
    {
        $stadium = GameStadium::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        if ($stadium) {
            return $stadium;
        }

        return GameStadium::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'base_capacity' => $game->team?->stadium_seats ?? 0,
        ]);
    }

    /** Currently in-flight project (pending or in_progress), if any. */
    public function activeProject(Game $game): ?GameStadiumProject
    {
        return GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->active()
            ->first();
    }

    public function rebuildCostPerSeat(): int
    {
        return (int) config('finances.stadium_costs.rebuild_per_seat_cents', 1_500_000);
    }

    public function supplementaryCostPerSeat(): int
    {
        return (int) config('finances.stadium_costs.supplementary_per_seat_cents', 800_000);
    }

    /**
     * Maximum capacity a rebuild can target given the loan cap and the
     * per-seat cost. The user can pay cash if they have it, but the cap
     * still applies — the bank's willingness to lend is the regulator's
     * proxy for "reasonable stadium size for a club of this stature".
     */
    public function maxRebuildCapacity(Game $game): int
    {
        $maxFunding = $this->loanService->maxLoanCap($game);
        $perSeat = $this->rebuildCostPerSeat();

        if ($perSeat <= 0) {
            return 0;
        }

        return intdiv($maxFunding, $perSeat);
    }

    public function canRebuild(Game $game): bool
    {
        if ($this->activeProject($game) !== null) {
            return false;
        }

        $level = TeamReputation::resolveLevel($game->id, $game->team_id);
        $minIndex = ClubProfile::getReputationTierIndex(self::REBUILD_MIN_REPUTATION);
        $currentIndex = ClubProfile::getReputationTierIndex($level);

        return $currentIndex >= $minIndex;
    }

    /**
     * Commit a supplementary-stands project. Capacity becomes live after
     * `supplementary_construction_days` calendar days; payment is upfront.
     */
    public function commitSupplementary(Game $game, int $seats): GameStadiumProject
    {
        if ($this->activeProject($game) !== null) {
            throw new InvalidArgumentException('messages.stadium_active_project_exists');
        }

        $stadium = $this->stadiumFor($game);

        if ($seats < 1) {
            throw new InvalidArgumentException('messages.stadium_supplementary_too_few_seats');
        }

        if ($seats > $stadium->supplementary_headroom) {
            throw new InvalidArgumentException('messages.stadium_supplementary_exceeds_cap');
        }

        $cost = $seats * $this->supplementaryCostPerSeat();
        $this->assertCashAvailable($game, $cost);

        $days = (int) config('finances.stadium_costs.supplementary_construction_days', 30);
        $completionDate = $game->current_date->copy()->addDays($days);

        return DB::transaction(function () use ($game, $seats, $cost, $completionDate) {
            $project = GameStadiumProject::create([
                'game_id' => $game->id,
                'team_id' => $game->team_id,
                'type' => GameStadiumProject::TYPE_SUPPLEMENTARY,
                'status' => GameStadiumProject::STATUS_IN_PROGRESS,
                'target_capacity' => $seats,
                'committed_season' => (int) $game->season,
                'committed_date' => $game->current_date,
                'completion_date' => $completionDate,
                'completion_season' => null,
                'total_cost_cents' => $cost,
                'financing' => GameStadiumProject::FINANCING_CASH,
                'paid_cents' => $cost,
            ]);

            $this->deductCash($game, $cost, __('finances.tx_stadium_supplementary_payment', [
                'seats' => $seats,
                'amount' => Money::format($cost),
            ]));

            $this->notificationService->notifyStadiumProjectCommitted(
                $game,
                GameStadiumProject::TYPE_SUPPLEMENTARY,
                $seats,
                $completionDate->isoFormat('LL'),
            );

            return $project;
        });
    }

    /**
     * Commit a full stadium rebuild. Construction begins next season at
     * 40% capacity and the new capacity goes live the season after.
     */
    public function commitRebuild(
        Game $game,
        int $targetCapacity,
        string $financing,
    ): GameStadiumProject {
        if ($this->activeProject($game) !== null) {
            throw new InvalidArgumentException('messages.stadium_active_project_exists');
        }

        if (! $this->canRebuild($game)) {
            throw new InvalidArgumentException('messages.stadium_rebuild_reputation_too_low');
        }

        $stadium = $this->stadiumFor($game);
        if ($targetCapacity <= $stadium->effective_capacity) {
            throw new InvalidArgumentException('messages.stadium_rebuild_must_be_larger');
        }

        $maxCap = $this->maxRebuildCapacity($game);
        if ($targetCapacity > $maxCap) {
            throw new InvalidArgumentException('messages.stadium_rebuild_exceeds_max_capacity');
        }

        if (! in_array($financing, [
            GameStadiumProject::FINANCING_CASH,
            GameStadiumProject::FINANCING_LOAN,
        ], true)) {
            throw new InvalidArgumentException('messages.stadium_invalid_financing');
        }

        $cost = $targetCapacity * $this->rebuildCostPerSeat();

        if ($financing === GameStadiumProject::FINANCING_CASH) {
            $this->assertCashAvailable($game, $cost);
        }

        return DB::transaction(function () use ($game, $targetCapacity, $cost, $financing) {
            $committedSeason = (int) $game->season;

            $project = GameStadiumProject::create([
                'game_id' => $game->id,
                'team_id' => $game->team_id,
                'type' => GameStadiumProject::TYPE_REBUILD,
                'status' => GameStadiumProject::STATUS_PENDING,
                'target_capacity' => $targetCapacity,
                'committed_season' => $committedSeason,
                'committed_date' => $game->current_date,
                'completion_date' => null,
                // One off-season + one construction season; new capacity
                // goes live at the start of committed_season + 2.
                'completion_season' => $committedSeason + 2,
                'total_cost_cents' => $cost,
                'financing' => $financing,
                'paid_cents' => $financing === GameStadiumProject::FINANCING_CASH ? $cost : 0,
            ]);

            if ($financing === GameStadiumProject::FINANCING_CASH) {
                $this->deductCash($game, $cost, __('finances.tx_stadium_rebuild_payment', [
                    'capacity' => number_format($targetCapacity, 0, ',', '.'),
                    'amount' => Money::format($cost),
                ]));
            } else {
                $this->loanService->request($game, $project, $cost);
            }

            $this->notificationService->notifyStadiumProjectCommitted(
                $game,
                GameStadiumProject::TYPE_REBUILD,
                $targetCapacity,
                (string) ($committedSeason + 2),
            );

            return $project->fresh();
        });
    }

    /**
     * Cash actually free to commit to a stadium project right now: the
     * transfer budget minus what's already promised to in-flight offers.
     * Returns 0 when the game has no current investment / no projections.
     */
    public function availableCashFor(Game $game): int
    {
        $investment = $game->currentInvestment;

        if (! $investment) {
            return 0;
        }

        return max(0, $investment->transfer_budget - TransferOffer::committedBudget($game->id));
    }

    private function assertCashAvailable(Game $game, int $cost): void
    {
        if (! $game->currentInvestment) {
            throw new InvalidArgumentException('messages.budget_no_projections');
        }

        if ($cost > $this->availableCashFor($game)) {
            throw new InvalidArgumentException('messages.stadium_insufficient_budget');
        }
    }

    private function deductCash(Game $game, int $cost, string $description): void
    {
        $investment = $game->currentInvestment;
        $investment->decrement('transfer_budget', $cost);

        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_INFRASTRUCTURE,
            amount: $cost,
            description: $description,
            transactionDate: $game->current_date->toDateString(),
        );
    }
}
