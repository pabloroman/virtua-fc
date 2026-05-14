<?php

namespace App\Http\Views;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Models\TeamReputation;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use App\Modules\Stadium\Services\StadiumSummaryService;
use App\Modules\Stadium\Services\StadiumUpgradeService;
use App\Modules\Stadium\UefaCategory;
use App\Support\Money;

class ShowClubStadium
{
    public function __construct(
        private readonly StadiumSummaryService $stadiumSummaryService,
        private readonly StadiumUpgradeService $stadiumUpgradeService,
        private readonly StadiumLoanService $stadiumLoanService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $stadium = $this->stadiumUpgradeService->stadiumFor($game);
        $activeProject = $this->stadiumUpgradeService->activeProject($game);
        $reputationLevel = TeamReputation::resolveLevel($game->id, $game->team_id);

        // Decompose the two loan ceilings so the view can tell the user
        // *which* lever is binding when the rebuild CTA is locked, and
        // point at the specific threshold needed to unlock it. Cache
        // `loanCap` here so we don't reach back through maxLoanCap()
        // (which re-resolves reputation + currentFinances) for every
        // dependent calculation below.
        $reputationCap = $this->stadiumLoanService->reputationLoanCap($reputationLevel);
        $affordabilityCap = $this->stadiumLoanService->affordabilityLoanCap($game);
        $loanCap = min($reputationCap, $affordabilityCap);
        $rebuildBands = (array) config('finances.stadium_costs.rebuild_per_seat_bands', []);
        $rebuildEntryPerSeat = (int) ($rebuildBands[0]['per_seat_cents'] ?? 1_500_000);
        $rebuildMaxCapacity = $this->stadiumUpgradeService->maxRebuildCapacityForBudget($loanCap);
        $currentCapacity = $stadium->effective_capacity;

        $bindingConstraint = null;
        $nextReputationTier = null;
        $revenueRequiredCents = null;

        if ($rebuildMaxCapacity <= $currentCapacity) {
            $bindingConstraint = $reputationCap <= $affordabilityCap ? 'reputation' : 'affordability';

            if ($bindingConstraint === 'reputation') {
                $nextReputationTier = $this->nextReputationTier($reputationLevel);
            } else {
                $requiredPrincipal = $this->stadiumUpgradeService->rebuildCostFor($currentCapacity + 1);
                $revenueRequiredCents = $this->stadiumLoanService->revenueRequiredForPrincipal($requiredPrincipal);
            }
        }

        $availableBudgetCents = $this->stadiumUpgradeService->availableCashFor($game);

        $supplementaryPerSeat = $this->stadiumUpgradeService->supplementaryCostPerSeat();
        $supplementaryProjectCap = $this->stadiumUpgradeService->supplementaryMaxSeatsPerProject();

        // Cap supplementary seats at what cash can buy, rounded down to the
        // slider's 100-seat step so the slider doesn't overshoot.
        $affordableSupplementarySeats = $supplementaryPerSeat > 0
            ? (int) (floor(intdiv($availableBudgetCents, $supplementaryPerSeat) / 100) * 100)
            : 0;
        $supplementaryEffectiveMax = min(
            $stadium->supplementary_headroom,
            $supplementaryProjectCap,
            $affordableSupplementarySeats,
        );

        // Stand expansion: cash-financed projects clamp by available
        // budget; loan-financed projects clamp by the loan ceiling.
        $standExpansionPerSeat = $this->stadiumUpgradeService->standExpansionCostPerSeat();
        $standExpansionMaxSeats = $this->stadiumUpgradeService->standExpansionMaxSeats();
        $standExpansionMinSeats = $this->stadiumUpgradeService->standExpansionMinSeats();
        $standExpansionCashMax = $standExpansionPerSeat > 0
            ? min($standExpansionMaxSeats, (int) (floor(intdiv($availableBudgetCents, $standExpansionPerSeat) / 500) * 500))
            : 0;
        $standExpansionLoanMax = $standExpansionPerSeat > 0
            ? min($standExpansionMaxSeats, (int) (floor(intdiv($loanCap, $standExpansionPerSeat) / 500) * 500))
            : 0;

        // Cash-financed rebuilds use the same bracket math as loan-financed
        // ones, just clamped by available cash instead of the loan ceiling.
        // Snap down to the slider's 1,000-seat step so the slider doesn't
        // overshoot what cash can buy.
        $affordableRebuildRaw = $this->stadiumUpgradeService->maxRebuildCapacityForBudget($availableBudgetCents);
        $affordableRebuildCapacity = (int) (floor($affordableRebuildRaw / 1_000) * 1_000);

        // UEFA category upgrade context: current level, next-step target,
        // flat cost, capacity floor for the target, and the blocker code
        // (if any) so the modal/CTA can render the right hint without
        // recomputing the rules.
        $currentUefaLevel = $stadium->effective_uefa_level;
        $nextUefaLevel = $currentUefaLevel !== null && $currentUefaLevel < UefaCategory::MAX
            ? $currentUefaLevel + 1
            : null;
        $uefaUpgradeCost = $currentUefaLevel !== null
            ? $this->stadiumUpgradeService->uefaUpgradeCost($currentUefaLevel)
            : 0;
        $uefaCapacityFloor = $nextUefaLevel !== null
            ? UefaCategory::capacityFloor($nextUefaLevel)
            : 0;
        $uefaBlocker = $this->stadiumUpgradeService->uefaUpgradeBlocker($game);
        $uefaCashAffordable = $uefaUpgradeCost > 0 && $uefaUpgradeCost <= $availableBudgetCents;
        $uefaLoanAffordable = $uefaUpgradeCost > 0 && $uefaUpgradeCost <= $loanCap;

        // Slider mins/steps and the per-CTA affordability flags previously
        // lived in an @php block at the top of stadium-upgrades.blade.php.
        // Computing them here keeps the template presentation-only.
        $supplementaryMin = 500;
        $supplementaryStep = 100;
        $supplementaryAffordable = $supplementaryEffectiveMax >= $supplementaryMin;
        $supplementaryNaturalMax = min($stadium->supplementary_headroom, $supplementaryProjectCap);

        $standExpansionStep = 500;
        $standExpansionCashAffordable = $standExpansionCashMax >= $standExpansionMinSeats;
        $standExpansionLoanAffordable = $standExpansionLoanMax >= $standExpansionMinSeats;
        $standExpansionAvailable = $standExpansionCashAffordable || $standExpansionLoanAffordable;

        $rebuildMin = $currentCapacity + 1000;
        $rebuildStep = 1000;
        $rebuildCashAffordable = $affordableRebuildCapacity >= $rebuildMin;
        $canRebuild = $this->stadiumUpgradeService->canRebuild($game);
        // CTA opens whenever the loan-financed path is reachable; cash is a
        // strictly weaker constraint, so the modal alone decides which of
        // the two financing radios to disable.
        $rebuildAvailable = $canRebuild && $rebuildMaxCapacity >= $rebuildMin;

        $uefaAvailable = $uefaBlocker === null
            && ($uefaCashAffordable || $uefaLoanAffordable);

        $upgrade = [
            'stadium' => $stadium,
            'active_project' => $activeProject,
            'active_loan' => $activeProject?->loan,

            // UEFA category upgrade.
            'uefa_current_level' => $currentUefaLevel,
            'uefa_next_level' => $nextUefaLevel,
            'uefa_upgrade_cost_cents' => $uefaUpgradeCost,
            'uefa_capacity_floor' => $uefaCapacityFloor,
            'uefa_blocker' => $uefaBlocker,
            'uefa_cash_affordable' => $uefaCashAffordable,
            'uefa_loan_affordable' => $uefaLoanAffordable,
            'uefa_available' => $uefaAvailable,

            // Supplementary stands (modular bleachers).
            'supplementary_headroom' => $stadium->supplementary_headroom,
            'supplementary_effective_max' => $supplementaryEffectiveMax,
            'supplementary_per_seat_cents' => $supplementaryPerSeat,
            'supplementary_min' => $supplementaryMin,
            'supplementary_step' => $supplementaryStep,
            'supplementary_affordable' => $supplementaryAffordable,
            'supplementary_natural_max' => $supplementaryNaturalMax,

            // Stand expansion (permanent single-stand rebuild).
            'stand_expansion_per_seat_cents' => $standExpansionPerSeat,
            'stand_expansion_min_seats' => $standExpansionMinSeats,
            'stand_expansion_max_seats' => $standExpansionMaxSeats,
            'stand_expansion_cash_max' => $standExpansionCashMax,
            'stand_expansion_loan_max' => $standExpansionLoanMax,
            'stand_expansion_step' => $standExpansionStep,
            'stand_expansion_cash_affordable' => $standExpansionCashAffordable,
            'stand_expansion_loan_affordable' => $standExpansionLoanAffordable,
            'stand_expansion_available' => $standExpansionAvailable,

            // Full rebuild (cumulative bracket pricing).
            'rebuild_cost_bands' => $rebuildBands,
            'rebuild_entry_per_seat_cents' => $rebuildEntryPerSeat,
            'rebuild_max_capacity_cash' => $affordableRebuildCapacity,
            'can_rebuild' => $canRebuild,
            'rebuild_max_capacity' => $rebuildMaxCapacity,
            'rebuild_min' => $rebuildMin,
            'rebuild_step' => $rebuildStep,
            'rebuild_cash_affordable' => $rebuildCashAffordable,
            'rebuild_available' => $rebuildAvailable,

            'loan_cap_cents' => $loanCap,
            'available_budget_cents' => $availableBudgetCents,
            'current_capacity' => $currentCapacity,
            'reputation_level' => $reputationLevel,
            'reputation_cap_cents' => $reputationCap,
            'affordability_cap_cents' => $affordabilityCap,
            'binding_constraint' => $bindingConstraint,           // 'reputation' | 'affordability' | null
            'next_reputation_tier' => $nextReputationTier,         // string | null
            'revenue_required_cents' => $revenueRequiredCents,    // int | null
        ];

        // Renovation history — chronological diary of every project ever
        // committed, regardless of status. Active projects sort to the top
        // via committed_date desc. Capped to keep long-running saves from
        // streaming hundreds of rows into the page.
        $projectHistory = GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->orderByDesc('committed_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $historyRows = $projectHistory->map(fn (GameStadiumProject $project) => $this->buildHistoryRow($project))->all();

        return view('club.stadium', [
            'game' => $game,
            'upgrade' => $upgrade,
            'historyRows' => $historyRows,
            ...$this->stadiumSummaryService->build($game),
        ]);
    }

    /**
     * Pre-render every history row server-side so the Blade template
     * stays presentation-only — no @php branches, no `match` on the
     * project type. Keys mirror the columns rendered by
     * stadium-history.blade.php.
     *
     * @return array{
     *   type_label: string,
     *   detail: string,
     *   cost_label: string,
     *   status_label: string,
     *   ready_label: string,
     *   is_completed: bool,
     * }
     */
    private function buildHistoryRow(GameStadiumProject $project): array
    {
        // Additive projects (supplementary, stand expansion) display as
        // "+N"; rebuild's target_capacity is a total, not a delta. UEFA
        // upgrades store the destination level (1–4), not a seat count.
        $detail = match ($project->type) {
            StadiumProjectType::Supplementary,
            StadiumProjectType::StandExpansion
                => '+'.number_format($project->target_capacity),
            StadiumProjectType::Rebuild
                => __('club.stadium.history.detail_rebuild', ['count' => number_format($project->target_capacity)]),
            StadiumProjectType::UefaUpgrade
                => __('club.stadium.history.detail_uefa_upgrade', [
                    'from' => max(1, (int) $project->target_capacity - 1),
                    'to'   => (int) $project->target_capacity,
                ]),
        };

        $isCompleted = $project->status === StadiumProjectStatus::Completed;

        // "When is it ready" label varies by project shape: supplementary
        // lands on a calendar date, the others land at the start of a
        // season.
        $readyLabel = $project->completion_date
            ? $project->completion_date->isoFormat('LL')
            : ($project->completion_season
                ? __('club.stadium.history.season_label', ['season' => $project->completion_season])
                : '—');

        return [
            'type_label' => __('club.stadium.upgrades.project_'.$project->type->value),
            'detail' => $detail,
            'cost_label' => Money::format($project->total_cost_cents),
            'is_completed' => $isCompleted,
            'status_label' => __($isCompleted
                ? 'club.stadium.history.status_completed'
                : 'club.stadium.history.status_in_progress'),
            'ready_label' => $isCompleted
                ? $readyLabel
                : __('club.stadium.history.ready_label', ['date' => $readyLabel]),
        ];
    }

    private function nextReputationTier(string $current): ?string
    {
        $tiers = ClubProfile::REPUTATION_TIERS;
        $index = array_search($current, $tiers, true);

        if ($index === false || $index === count($tiers) - 1) {
            return null; // already at ELITE
        }

        return $tiers[$index + 1];
    }
}
