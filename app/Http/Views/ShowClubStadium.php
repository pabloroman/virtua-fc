<?php

namespace App\Http\Views;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Models\TeamReputation;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use App\Modules\Stadium\Services\NamingRightsReadService;
use App\Modules\Stadium\Services\StadiumSummaryService;
use App\Modules\Stadium\Services\StadiumUpgradeService;
use App\Modules\Stadium\UefaCategory;
use App\Support\Money;
use Illuminate\Support\Number;

class ShowClubStadium
{
    public function __construct(
        private readonly StadiumSummaryService $stadiumSummaryService,
        private readonly StadiumUpgradeService $stadiumUpgradeService,
        private readonly StadiumLoanService $stadiumLoanService,
        private readonly NamingRightsReadService $namingRightsReadService,
        private readonly BudgetProjectionService $budgetProjectionService,
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
        $rebuildBands = (array) config('stadium.stadium_costs.rebuild_per_seat_bands', []);
        $rebuildEntryPerSeat = (int) ($rebuildBands[0]['per_seat_cents'] ?? 1_500_000);
        $rebuildMaxCapacity = $this->stadiumUpgradeService->maxRebuildCapacityForBudget($loanCap);
        $currentCapacity = $stadium->effective_capacity;

        // Which loan cap is binding — surfaced in both the locked-state
        // unlock CTAs and the rebuild modal's "why is this the max?"
        // explainer. Always computed so the modal can render it even when
        // the rebuild is reachable. Ties go to reputation (the easier
        // mental model for users to act on).
        $bindingConstraint = $reputationCap <= $affordabilityCap ? 'reputation' : 'affordability';

        $nextReputationTier = null;
        $revenueRequiredCents = null;

        if ($rebuildMaxCapacity <= $currentCapacity) {
            if ($bindingConstraint === 'reputation') {
                $nextReputationTier = $this->nextReputationTier($reputationLevel);
            } else {
                $requiredPrincipal = $this->stadiumUpgradeService->rebuildCostFor($currentCapacity + 1);
                $revenueRequiredCents = $this->stadiumLoanService->revenueRequiredForPrincipal($requiredPrincipal);
            }
        }

        $availableBudgetCents = $this->stadiumUpgradeService->availableCashFor($game);

        // Pre-format the estimated completion date for each project type
        // so the modals can render a "Fecha de finalización" row without
        // any date math in the template. Each project's construction
        // window is calendar-fixed at commit time, so adding the same
        // window to current_date here matches what the user will see in
        // the history once they commit.
        $supplementaryCompletionLabel = $this->formatCompletionDate(
            $game->current_date->copy()->addDays($this->stadiumUpgradeService->supplementaryConstructionDays())
        );
        $standExpansionCompletionLabel = $this->formatCompletionDate(
            $game->current_date->copy()->addDays($this->stadiumUpgradeService->standExpansionConstructionDays())
        );
        $rebuildCompletionLabel = $this->formatCompletionDate(
            $game->current_date->copy()->addDays($this->stadiumUpgradeService->rebuildConstructionDays())
        );
        $uefaCompletionLabel = $this->formatCompletionDate(
            $game->current_date->copy()->addDays($this->stadiumUpgradeService->uefaUpgradeConstructionDays())
        );

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

        // "From total" hero numbers surfaced on the tier cards. Each is the
        // cheapest realistic total the player can commit to at that tier —
        // not a per-seat rate — so the price-at-a-glance reads as a single
        // figure instead of three lines of prose.
        $supplementaryMinTotalCents = $supplementaryMin * $supplementaryPerSeat;
        $standExpansionMinTotalCents = $standExpansionMinSeats * $standExpansionPerSeat;
        $rebuildMinTotalCents = $this->stadiumUpgradeService->rebuildCostFor($rebuildMin);

        // Projected operating revenue drives the affordability-lock progress
        // bar on the rebuild card. Lazy-loaded per CLAUDE.md; if there is no
        // current finances row yet (very early-season edge), surface 0 so
        // the bar renders empty rather than blowing up.
        $currentAnnualRevenueCents = (int) ($game->currentFinances?->projected_total_revenue ?? 0);

        // Per-card display state — single source of truth the partial reads
        // when picking border color, status badge, hover/disabled treatment,
        // and goal-line variant. Keeping derivation here (not in a Blade @php
        // block) preserves the project rule that templates stay logic-free.
        //
        // The partial hides the row list entirely while a project is in
        // flight (the history card carries the in-progress info), so the
        // matches below assume there is no active project.
        //
        // Values:
        //   'locked_reputation'    — rebuild only; need a higher reputation tier
        //   'locked_affordability' — rebuild only; need more annual revenue
        //   'locked'               — generic gate (not enough cash / no headroom)
        //   'available_loan'       — affordable only via stadium loan (stretches budget)
        //   'available_cash'       — comfortably within current cash budget
        $supplementaryState = match (true) {
            ! $supplementaryAffordable              => 'locked',
            default                                 => 'available_cash',
        };

        $standExpansionState = match (true) {
            ! $standExpansionAvailable              => 'locked',
            ! $standExpansionCashAffordable         => 'available_loan',
            default                                 => 'available_cash',
        };

        $rebuildState = match (true) {
            ! $canRebuild                           => 'locked_reputation',
            $rebuildMaxCapacity <= $currentCapacity && $bindingConstraint === 'reputation'
                                                    => 'locked_reputation',
            $rebuildMaxCapacity <= $currentCapacity => 'locked_affordability',
            ! $rebuildCashAffordable                => 'available_loan',
            default                                 => 'available_cash',
        };

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
            'supplementary_min_total_cents' => $supplementaryMinTotalCents,
            'supplementary_step' => $supplementaryStep,
            'supplementary_affordable' => $supplementaryAffordable,
            'supplementary_natural_max' => $supplementaryNaturalMax,
            'supplementary_state' => $supplementaryState,

            // Stand expansion (permanent single-stand rebuild).
            'stand_expansion_per_seat_cents' => $standExpansionPerSeat,
            'stand_expansion_min_seats' => $standExpansionMinSeats,
            'stand_expansion_min_total_cents' => $standExpansionMinTotalCents,
            'stand_expansion_max_seats' => $standExpansionMaxSeats,
            'stand_expansion_cash_max' => $standExpansionCashMax,
            'stand_expansion_loan_max' => $standExpansionLoanMax,
            'stand_expansion_step' => $standExpansionStep,
            'stand_expansion_cash_affordable' => $standExpansionCashAffordable,
            'stand_expansion_loan_affordable' => $standExpansionLoanAffordable,
            'stand_expansion_available' => $standExpansionAvailable,
            'stand_expansion_state' => $standExpansionState,

            // Full rebuild (cumulative bracket pricing).
            'rebuild_cost_bands' => $rebuildBands,
            'rebuild_entry_per_seat_cents' => $rebuildEntryPerSeat,
            'rebuild_max_capacity_cash' => $affordableRebuildCapacity,
            'can_rebuild' => $canRebuild,
            'rebuild_max_capacity' => $rebuildMaxCapacity,
            'rebuild_min' => $rebuildMin,
            'rebuild_min_total_cents' => $rebuildMinTotalCents,
            'rebuild_step' => $rebuildStep,
            'rebuild_cash_affordable' => $rebuildCashAffordable,
            'rebuild_available' => $rebuildAvailable,
            'rebuild_state' => $rebuildState,

            'supplementary_completion_label' => $supplementaryCompletionLabel,
            'stand_expansion_completion_label' => $standExpansionCompletionLabel,
            'rebuild_completion_label' => $rebuildCompletionLabel,
            'uefa_completion_label' => $uefaCompletionLabel,

            'loan_cap_cents' => $loanCap,
            'available_budget_cents' => $availableBudgetCents,
            'current_annual_revenue_cents' => $currentAnnualRevenueCents,
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

        // The fixed inputs to the walk-up matchday projection (everything bar
        // the season-ticket holder count, which varies per preset). Handed to
        // the season-ticket editor so it can recompute the taquilla figure
        // client-side as the user toggles presets — no save round-trip. Pulled
        // from Finance here in the HTTP layer; the Stadium module itself stays
        // free of any Finance dependency.
        $matchdayFactors = $this->budgetProjectionService->matchdayProjectionFactors($game->team, $game);

        return view('club.stadium', [
            'game' => $game,
            'upgrade' => $upgrade,
            'historyRows' => $historyRows,
            'matchdayFactors' => $matchdayFactors,
            ...$this->stadiumSummaryService->build($game),
            ...$this->namingRightsReadService->buildIdentityPanel($game),
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
                => __('club.stadium.history.detail_seats', ['count' => '+'.Number::format($project->target_capacity)]),
            StadiumProjectType::Rebuild
                => __('club.stadium.history.detail_rebuild', ['count' => Number::format($project->target_capacity)]),
            StadiumProjectType::UefaUpgrade
                => __('club.stadium.history.detail_uefa_upgrade', [
                    'from' => max(1, (int) $project->target_capacity - 1),
                    'to'   => (int) $project->target_capacity,
                ]),
        };

        $isCompleted = $project->status === StadiumProjectStatus::Completed;

        // Every project type now lands on a fixed completion_date stamped
        // at commit. Pre-refactor records that still only carry
        // completion_season fall back to the legacy season label.
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

    /**
     * Render a completion date in the modal-friendly "30 Junio 2027"
     * style — day, capitalised month name, year — using the configured
     * app locale. Falls through to the raw isoFormat output for locales
     * where mb_convert_case mis-cases month names.
     */
    private function formatCompletionDate(\Carbon\Carbon|\Illuminate\Support\Carbon $date): string
    {
        $label = $date->isoFormat('D MMMM YYYY');

        return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
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
