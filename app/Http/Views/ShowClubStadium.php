<?php

namespace App\Http\Views;

use App\Models\ClubProfile;
use App\Models\Game;
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
        $rebuildPerSeat = $this->stadiumUpgradeService->rebuildCostPerSeat();
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
                // Revenue needed to push the affordability cap past
                // (currentCapacity + 1) × perSeatCost. Inverse of
                // affordabilityLoanCap: revenue = principal × (1/term + rate) / maxDebtServicePct.
                $requiredPrincipal = ($currentCapacity + 1) * $rebuildPerSeat;
                $termYears = (int) config('finances.stadium_loan.term_years', 10);
                $rateBps = (int) config('finances.stadium_loan.interest_rate_bps', 400);
                $maxPct = (float) config('finances.stadium_loan.max_debt_service_pct', 0.25);
                $firstYearRate = (1 / $termYears) + ($rateBps / 10000);
                $revenueRequiredCents = (int) ceil($requiredPrincipal * $firstYearRate / $maxPct);
            }
        }

        $upgrade = [
            'stadium' => $stadium,
            'active_project' => $activeProject,
            'active_loan' => $activeProject
                ? $this->stadiumLoanService->activeLoanForProject($activeProject)
                : null,
            'supplementary_headroom' => $stadium->supplementary_headroom,
            'supplementary_per_seat_cents' => $this->stadiumUpgradeService->supplementaryCostPerSeat(),
            'rebuild_per_seat_cents' => $rebuildPerSeat,
            'can_rebuild' => $this->stadiumUpgradeService->canRebuild($game),
            'rebuild_max_capacity' => $rebuildMaxCapacity,
            'loan_cap_cents' => $this->stadiumLoanService->maxLoanCap($game),
            'reputation_level' => $reputationLevel,
            'reputation_cap_cents' => $reputationCap,
            'affordability_cap_cents' => $affordabilityCap,
            'binding_constraint' => $bindingConstraint,           // 'reputation' | 'affordability' | null
            'next_reputation_tier' => $nextReputationTier,         // string | null
            'revenue_required_cents' => $revenueRequiredCents,    // int | null
        ];

        return view('club.stadium', [
            'game' => $game,
            'summary' => $this->stadiumSummaryService->build($game),
            'upgrade' => $upgrade,
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
