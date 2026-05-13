<?php

namespace App\Http\Views;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Models\TeamReputation;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Finance\Services\StadiumUpgradeService;
use App\Modules\Stadium\Services\StadiumSummaryService;

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
        // point at the specific threshold needed to unlock it.
        $reputationCap = $this->stadiumLoanService->reputationLoanCap($reputationLevel);
        $affordabilityCap = $this->stadiumLoanService->affordabilityLoanCap($game);
        $rebuildBands = (array) config('finances.stadium_costs.rebuild_per_seat_bands', []);
        $rebuildEntryPerSeat = (int) ($rebuildBands[0]['per_seat_cents'] ?? 1_500_000);
        $rebuildMaxCapacity = $this->stadiumUpgradeService->maxRebuildCapacity($game);
        $currentCapacity = $stadium->effective_capacity;

        $bindingConstraint = null;
        $nextReputationTier = null;
        $revenueRequiredCents = null;

        if ($rebuildMaxCapacity <= $currentCapacity) {
            // Loan cap is too small to build bigger than the current
            // stadium. Identify which cap is dragging it down.
            $bindingConstraint = $reputationCap <= $affordabilityCap ? 'reputation' : 'affordability';

            if ($bindingConstraint === 'reputation') {
                $nextReputationTier = $this->nextReputationTier($reputationLevel);
            } else {
                // Revenue needed to push the affordability cap past the
                // cost of building one more seat than the current stadium.
                // Use the entry-band per-seat rate as a lower bound — the
                // smallest possible incremental cost.
                $requiredPrincipal = $this->stadiumUpgradeService->rebuildCostFor($currentCapacity + 1);
                $termYears = (int) config('finances.stadium_loan.term_years', 10);
                $rateBps = (int) config('finances.stadium_loan.interest_rate_bps', 400);
                $maxPct = (float) config('finances.stadium_loan.max_debt_service_pct', 0.25);
                $firstYearRate = (1 / $termYears) + ($rateBps / 10000);
                $revenueRequiredCents = (int) ceil($requiredPrincipal * $firstYearRate / $maxPct);
            }
        }

        // Cash actually free for stadium projects right now. Used by the
        // modals to clamp the sliders so the user can never queue a project
        // they can't afford, and to disable the CTAs entirely when not even
        // the minimum project size is reachable.
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
        $loanCap = $this->stadiumLoanService->maxLoanCap($game);
        $standExpansionCashMax = $standExpansionPerSeat > 0
            ? (int) (floor(intdiv($availableBudgetCents, $standExpansionPerSeat) / 500) * 500)
            : 0;
        $standExpansionLoanMax = $standExpansionPerSeat > 0
            ? (int) (floor(intdiv($loanCap, $standExpansionPerSeat) / 500) * 500)
            : 0;
        // Cap loan-financed expansions at the design ceiling regardless of
        // how much the bank would lend.
        $standExpansionCashMax = min($standExpansionCashMax, $standExpansionMaxSeats);
        $standExpansionLoanMax = min($standExpansionLoanMax, $standExpansionMaxSeats);

        // Cash-financed rebuilds use the same bracket math as loan-financed
        // ones, just clamped by available cash instead of the loan ceiling.
        // Snap down to the slider's 1,000-seat step so the slider doesn't
        // overshoot what cash can buy.
        $affordableRebuildRaw = $this->stadiumUpgradeService->maxRebuildCapacityForBudget($availableBudgetCents);
        $affordableRebuildCapacity = (int) (floor($affordableRebuildRaw / 1_000) * 1_000);

        $upgrade = [
            'stadium' => $stadium,
            'active_project' => $activeProject,
            'active_loan' => $activeProject
                ? $this->stadiumLoanService->activeLoanForProject($activeProject)
                : null,

            // Supplementary stands (modular bleachers).
            'supplementary_headroom' => $stadium->supplementary_headroom,
            'supplementary_effective_max' => $supplementaryEffectiveMax,
            'supplementary_per_seat_cents' => $supplementaryPerSeat,
            'supplementary_project_cap' => $supplementaryProjectCap,

            // Stand expansion (permanent single-stand rebuild).
            'stand_expansion_per_seat_cents' => $standExpansionPerSeat,
            'stand_expansion_min_seats' => $standExpansionMinSeats,
            'stand_expansion_max_seats' => $standExpansionMaxSeats,
            'stand_expansion_cash_max' => $standExpansionCashMax,
            'stand_expansion_loan_max' => $standExpansionLoanMax,

            // Full rebuild (cumulative bracket pricing).
            'rebuild_cost_bands' => $rebuildBands,
            'rebuild_entry_per_seat_cents' => $rebuildEntryPerSeat,
            'rebuild_max_capacity_cash' => $affordableRebuildCapacity,
            'can_rebuild' => $this->stadiumUpgradeService->canRebuild($game),
            'rebuild_max_capacity' => $rebuildMaxCapacity,

            'loan_cap_cents' => $loanCap,
            'available_budget_cents' => $availableBudgetCents,
            'reputation_level' => $reputationLevel,
            'reputation_cap_cents' => $reputationCap,
            'affordability_cap_cents' => $affordabilityCap,
            'binding_constraint' => $bindingConstraint,           // 'reputation' | 'affordability' | null
            'next_reputation_tier' => $nextReputationTier,         // string | null
            'revenue_required_cents' => $revenueRequiredCents,    // int | null
        ];

        // Renovation history — chronological diary of every project ever
        // committed, regardless of status. Active projects sort to the top
        // via committed_date desc.
        $projectHistory = GameStadiumProject::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->orderByDesc('committed_date')
            ->orderByDesc('id')
            ->get();

        return view('club.stadium', [
            'game' => $game,
            'upgrade' => $upgrade,
            'projectHistory' => $projectHistory,
            ...$this->stadiumSummaryService->build($game),
        ]);
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
