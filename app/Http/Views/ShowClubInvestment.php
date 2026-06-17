<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\TransferOffer;
use App\Modules\Finance\Services\BudgetAllocationService;
use App\Modules\Finance\Services\InvestmentStateService;
use App\Support\Money;

class ShowClubInvestment
{
    private const AREAS = ['youth_academy', 'medical', 'scouting'];

    public function __construct(
        private readonly BudgetAllocationService $budgetService,
        private readonly InvestmentStateService $stateService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $budgetData = $this->budgetService->prepareBudgetData($game);
        $investment = $budgetData['investment'];

        // Budget available for in-season upgrades: the current transfer budget
        // minus money already committed to pending offers.
        $availableBudget = $investment
            ? $investment->transfer_budget - TransferOffer::committedBudget($game->id)
            : 0;

        return view('club.investment', [
            ...$budgetData,
            'game' => $game,
            'state' => $this->stateService->resolveState($game),
            'isPreSeason' => $this->stateService->isEditableFreely($game),
            'availableBudget' => $availableBudget,
            'areaData' => $this->buildAreaData($investment, $budgetData, $availableBudget),
        ]);
    }

    /**
     * Per-area rows for the locked (in-season) view: current tier/amount, the
     * upgrade options (full cost, affordability), the lower tiers a downgrade
     * can be staged to, and any downgrade already staged for next season.
     *
     * @param  array{tierThresholds: array<string, array<int, int>>, minimumTier: int}  $budgetData
     * @return list<array<string, mixed>>
     */
    private function buildAreaData(?GameInvestment $investment, array $budgetData, int $availableBudget): array
    {
        if (! $investment) {
            return [];
        }

        $minimumTier = $budgetData['minimumTier'];
        $staged = $investment->staged_downgrades ?? [];
        $rows = [];

        foreach (self::AREAS as $area) {
            $tier = $investment->{"{$area}_tier"};
            $currentAmount = $investment->{"{$area}_amount"};

            $upgrades = [];
            for ($t = $tier + 1; $t <= 4; $t++) {
                $cost = $budgetData['tierThresholds'][$area][$t] - $currentAmount;
                $upgrades[] = [
                    'tier' => $t,
                    'cost' => Money::format($cost),
                    'affordable' => $cost <= $availableBudget,
                ];
            }

            $downgrades = [];
            for ($t = $minimumTier; $t < $tier; $t++) {
                $downgrades[] = $t;
            }

            $rows[] = [
                'key' => $area,
                'tier' => $tier,
                'amount' => $investment->{"formatted_{$area}_amount"},
                'upgrades' => $upgrades,
                'downgrades' => $downgrades,
                'staged' => $staged[$area] ?? null,
            ];
        }

        return $rows;
    }
}
