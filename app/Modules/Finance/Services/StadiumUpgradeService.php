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
use App\Modules\Stadium\UefaCategory;
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
            'base_uefa_level' => $game->team?->uefa_stadium_category,
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

    public function supplementaryCostPerSeat(): int
    {
        return (int) config('finances.stadium_costs.supplementary_per_seat_cents', 400_000);
    }

    public function supplementaryMaxSeatsPerProject(): int
    {
        return (int) config('finances.stadium_costs.supplementary_max_seats_per_project', 8_000);
    }

    public function standExpansionCostPerSeat(): int
    {
        return (int) config('finances.stadium_costs.stand_expansion_per_seat_cents', 800_000);
    }

    public function standExpansionMinSeats(): int
    {
        return (int) config('finances.stadium_costs.stand_expansion_min_seats', 3_000);
    }

    public function standExpansionMaxSeats(): int
    {
        return (int) config('finances.stadium_costs.stand_expansion_max_seats', 12_000);
    }

    /**
     * The most a stand-expansion project can target given (a) the design
     * cap and (b) what cash can buy. Loan-financed projects are clamped
     * separately by the loan cap.
     */
    public function affordableStandExpansionMaxSeats(Game $game): int
    {
        $perSeat = $this->standExpansionCostPerSeat();
        if ($perSeat <= 0) {
            return 0;
        }

        $cashCap = intdiv($this->availableCashFor($game), $perSeat);

        return min($this->standExpansionMaxSeats(), $cashCap);
    }

    /**
     * Bracket-priced rebuild cost (cumulative across bands). Per-seat
     * marginal cost grows with target size, but the total cost stays
     * continuous as the slider crosses band boundaries — no cliffs.
     *
     * @return int total cost in cents
     */
    public function rebuildCostFor(int $targetCapacity): int
    {
        if ($targetCapacity <= 0) {
            return 0;
        }

        $bands = $this->rebuildBands();
        $cost = 0;
        $remaining = $targetCapacity;
        $prevCap = 0;

        foreach ($bands as $band) {
            $upTo = $band['up_to'];
            $rate = (int) $band['per_seat_cents'];
            $bandSeats = $upTo === null ? $remaining : min($remaining, $upTo - $prevCap);

            if ($bandSeats <= 0) {
                if ($upTo !== null) {
                    $prevCap = $upTo;
                }
                continue;
            }

            $cost += $bandSeats * $rate;
            $remaining -= $bandSeats;
            $prevCap = $upTo ?? $prevCap;

            if ($remaining <= 0) {
                break;
            }
        }

        return $cost;
    }

    /**
     * Maximum capacity a rebuild can target given the loan cap (the bank's
     * proxy for "reasonable stadium size"). Delegates to
     * maxRebuildCapacityForBudget().
     */
    public function maxRebuildCapacity(Game $game): int
    {
        return $this->maxRebuildCapacityForBudget($this->loanService->maxLoanCap($game));
    }

    /**
     * Largest target capacity buildable under a given budget (in cents).
     * Walks the bracket array in order, consuming budget band-by-band
     * until the budget runs out, then adds the partial seats the remaining
     * budget can still pay for in the next band.
     */
    public function maxRebuildCapacityForBudget(int $budget): int
    {
        if ($budget <= 0) {
            return 0;
        }

        $bands = $this->rebuildBands();
        $capacity = 0;
        $prevCap = 0;

        foreach ($bands as $band) {
            $upTo = $band['up_to'];
            $rate = (int) $band['per_seat_cents'];
            if ($rate <= 0) {
                continue;
            }

            $bandWidth = $upTo === null ? PHP_INT_MAX : ($upTo - $prevCap);
            $bandCost = $bandWidth === PHP_INT_MAX ? PHP_INT_MAX : $bandWidth * $rate;

            if ($budget >= $bandCost) {
                $capacity += $bandWidth;
                $budget -= $bandCost;
                $prevCap = $upTo ?? $prevCap;
                continue;
            }

            // Budget runs out inside this band — buy as many seats as it can.
            $capacity += intdiv($budget, $rate);
            return $capacity;
        }

        return $capacity;
    }

    /**
     * @return array<int, array{up_to: int|null, per_seat_cents: int}>
     */
    private function rebuildBands(): array
    {
        $raw = config('finances.stadium_costs.rebuild_per_seat_bands');
        if (! is_array($raw) || $raw === []) {
            // Legacy fallback: behave like a single €15k band.
            return [['up_to' => null, 'per_seat_cents' => 1_500_000]];
        }

        return $raw;
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

        if ($seats > $this->supplementaryMaxSeatsPerProject()) {
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
                'seats' => number_format($seats, 0, ',', '.'),
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

        $cost = $this->rebuildCostFor($targetCapacity);

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
     * Commit a permanent single-stand expansion. Construction runs through
     * one season; the new seats go live at the start of the next season.
     * Unlike a full rebuild, the rest of the stadium stays open during
     * construction, so capacity is not reduced.
     */
    public function commitStandExpansion(
        Game $game,
        int $seats,
        string $financing,
    ): GameStadiumProject {
        if ($this->activeProject($game) !== null) {
            throw new InvalidArgumentException('messages.stadium_active_project_exists');
        }

        if ($seats < $this->standExpansionMinSeats()) {
            throw new InvalidArgumentException('messages.stadium_supplementary_too_few_seats');
        }

        if ($seats > $this->standExpansionMaxSeats()) {
            throw new InvalidArgumentException('messages.stadium_supplementary_exceeds_cap');
        }

        if (! in_array($financing, [
            GameStadiumProject::FINANCING_CASH,
            GameStadiumProject::FINANCING_LOAN,
        ], true)) {
            throw new InvalidArgumentException('messages.stadium_invalid_financing');
        }

        $cost = $seats * $this->standExpansionCostPerSeat();

        if ($financing === GameStadiumProject::FINANCING_CASH) {
            $this->assertCashAvailable($game, $cost);
        } else {
            // Reuse the rebuild loan cap as the bank's ceiling on
            // stand-expansion borrowing — same affordability/reputation
            // signal applies.
            if ($cost > $this->loanService->maxLoanCap($game)) {
                throw new InvalidArgumentException('messages.stadium_rebuild_exceeds_max_capacity');
            }
        }

        return DB::transaction(function () use ($game, $seats, $cost, $financing) {
            $committedSeason = (int) $game->season;

            $project = GameStadiumProject::create([
                'game_id' => $game->id,
                'team_id' => $game->team_id,
                'type' => GameStadiumProject::TYPE_STAND_EXPANSION,
                'status' => GameStadiumProject::STATUS_PENDING,
                'target_capacity' => $seats,
                'committed_season' => $committedSeason,
                'committed_date' => $game->current_date,
                'completion_date' => null,
                // New seats go live at the start of committed_season + 1.
                'completion_season' => $committedSeason + 1,
                'total_cost_cents' => $cost,
                'financing' => $financing,
                'paid_cents' => $financing === GameStadiumProject::FINANCING_CASH ? $cost : 0,
            ]);

            if ($financing === GameStadiumProject::FINANCING_CASH) {
                $this->deductCash($game, $cost, __('finances.tx_stadium_stand_expansion_payment', [
                    'seats' => number_format($seats, 0, ',', '.'),
                ]));
            } else {
                $this->loanService->request($game, $project, $cost);
            }

            $this->notificationService->notifyStadiumProjectCommitted(
                $game,
                GameStadiumProject::TYPE_STAND_EXPANSION,
                $seats,
                (string) ($committedSeason + 1),
            );

            return $project->fresh();
        });
    }

    /**
     * Flat cost (in cents) to bump the UEFA category one step starting
     * from `$fromLevel`. Returns 0 if there's no configured cost for that
     * transition (e.g. already at the top tier).
     */
    public function uefaUpgradeCost(int $fromLevel): int
    {
        $bands = (array) config('finances.stadium_costs.uefa_upgrade_cost_cents', []);

        return (int) ($bands[$fromLevel] ?? 0);
    }

    /**
     * Why the next UEFA upgrade can't be committed right now. Returns
     * null when an upgrade IS available. The view consumes the reason
     * code to render an explanatory hint on the CTA.
     *
     *   'already_max'      — already at Cat 4
     *   'capacity_floor'   — current capacity below the next category's
     *                        UEFA minimum
     *   'no_base_level'    — stadium has no UEFA category at all (e.g.
     *                        placeholder team / sub-200-seat ground)
     *   'active_project'   — another project is in flight
     */
    public function uefaUpgradeBlocker(Game $game): ?string
    {
        if ($this->activeProject($game) !== null) {
            return 'active_project';
        }

        $stadium = $this->stadiumFor($game);
        $current = $stadium->effective_uefa_level;

        if ($current === null) {
            return 'no_base_level';
        }

        if ($current >= UefaCategory::MAX) {
            return 'already_max';
        }

        $target = $current + 1;
        if ($stadium->effective_capacity < UefaCategory::capacityFloor($target)) {
            return 'capacity_floor';
        }

        return null;
    }

    /**
     * Commit a one-step UEFA category upgrade (current → current+1).
     * Construction runs through one season and the new category is live
     * at the start of the next season; capacity is unaffected during the
     * build (it's a facility fit-out, not a seat change).
     */
    public function commitUefaUpgrade(
        Game $game,
        string $financing,
    ): GameStadiumProject {
        $blocker = $this->uefaUpgradeBlocker($game);
        if ($blocker !== null) {
            throw new InvalidArgumentException(match ($blocker) {
                'active_project'  => 'messages.stadium_active_project_exists',
                'already_max'     => 'messages.stadium_uefa_already_max',
                'capacity_floor'  => 'messages.stadium_uefa_capacity_floor',
                'no_base_level'   => 'messages.stadium_uefa_no_base_level',
            });
        }

        if (! in_array($financing, [
            GameStadiumProject::FINANCING_CASH,
            GameStadiumProject::FINANCING_LOAN,
        ], true)) {
            throw new InvalidArgumentException('messages.stadium_invalid_financing');
        }

        $stadium = $this->stadiumFor($game);
        $currentLevel = (int) $stadium->effective_uefa_level;
        $targetLevel = $currentLevel + 1;

        $cost = $this->uefaUpgradeCost($currentLevel);
        if ($cost <= 0) {
            throw new InvalidArgumentException('messages.stadium_uefa_already_max');
        }

        if ($financing === GameStadiumProject::FINANCING_CASH) {
            $this->assertCashAvailable($game, $cost);
        } else {
            if ($cost > $this->loanService->maxLoanCap($game)) {
                throw new InvalidArgumentException('messages.stadium_rebuild_exceeds_max_capacity');
            }
        }

        return DB::transaction(function () use ($game, $targetLevel, $cost, $financing) {
            $committedSeason = (int) $game->season;

            $project = GameStadiumProject::create([
                'game_id' => $game->id,
                'team_id' => $game->team_id,
                'type' => GameStadiumProject::TYPE_UEFA_UPGRADE,
                'status' => GameStadiumProject::STATUS_PENDING,
                // target_capacity stores the target UEFA level (1–4) for
                // this project type. The history view branches on type
                // before rendering, so the integer never gets mistaken
                // for a seat count.
                'target_capacity' => $targetLevel,
                'committed_season' => $committedSeason,
                'committed_date' => $game->current_date,
                'completion_date' => null,
                'completion_season' => $committedSeason + 1,
                'total_cost_cents' => $cost,
                'financing' => $financing,
                'paid_cents' => $financing === GameStadiumProject::FINANCING_CASH ? $cost : 0,
            ]);

            if ($financing === GameStadiumProject::FINANCING_CASH) {
                $this->deductCash($game, $cost, __('finances.tx_stadium_uefa_upgrade_payment', [
                    'level' => $targetLevel,
                ]));
            } else {
                $this->loanService->request($game, $project, $cost);
            }

            $this->notificationService->notifyStadiumProjectCommitted(
                $game,
                GameStadiumProject::TYPE_UEFA_UPGRADE,
                $targetLevel,
                (string) ($committedSeason + 1),
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
            category: FinancialTransaction::CATEGORY_STADIUM,
            amount: $cost,
            description: $description,
            transactionDate: $game->current_date->toDateString(),
        );
    }
}
