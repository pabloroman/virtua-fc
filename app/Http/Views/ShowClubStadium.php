<?php

namespace App\Http\Views;

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

        $upgrade = [
            'stadium' => $stadium,
            'active_project' => $activeProject,
            'active_loan' => $activeProject
                ? $this->stadiumLoanService->activeLoanForProject($activeProject)
                : null,
            'supplementary_headroom' => $stadium->supplementary_headroom,
            'supplementary_per_seat_cents' => $this->stadiumUpgradeService->supplementaryCostPerSeat(),
            'rebuild_per_seat_cents' => $this->stadiumUpgradeService->rebuildCostPerSeat(),
            'can_rebuild' => $this->stadiumUpgradeService->canRebuild($game),
            'rebuild_max_capacity' => $this->stadiumUpgradeService->maxRebuildCapacity($game),
            'loan_cap_cents' => $this->stadiumLoanService->maxLoanCap($game),
            'reputation_level' => $reputationLevel,
        ];

        return view('club.stadium', [
            'game' => $game,
            'summary' => $this->stadiumSummaryService->build($game),
            'upgrade' => $upgrade,
        ]);
    }
}
