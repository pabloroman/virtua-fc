<?php

namespace App\Modules\Finance\Services;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\TransferOffer;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class InfrastructureUpgradeService
{
    private const VALID_AREAS = ['youth_academy', 'medical', 'scouting', 'facilities'];

    /**
     * Upgrade an infrastructure area to a higher tier, deducting the cost from the transfer budget.
     *
     * @throws \InvalidArgumentException
     */
    public function upgrade(Game $game, string $area, int $targetTier): GameInvestment
    {
        if (! in_array($area, self::VALID_AREAS, true)) {
            throw new \InvalidArgumentException(__('messages.infrastructure_upgrade_invalid_area'));
        }

        $investment = $game->currentInvestment;

        if (! $investment) {
            throw new \InvalidArgumentException(__('messages.budget_no_projections'));
        }

        $currentTier = $investment->{"{$area}_tier"};
        $currentAmount = $investment->{"{$area}_amount"};

        if ($targetTier <= $currentTier) {
            throw new \InvalidArgumentException(__('messages.infrastructure_upgrade_not_higher'));
        }

        if ($targetTier > 4) {
            throw new \InvalidArgumentException(__('messages.infrastructure_upgrade_max_tier'));
        }

        $newAmount = GameInvestment::TIER_THRESHOLDS[$area][$targetTier];
        $cost = $newAmount - $currentAmount;

        $availableBudget = $investment->transfer_budget - TransferOffer::committedBudget($game->id);

        if ($cost > $availableBudget) {
            throw new \InvalidArgumentException(
                __('messages.infrastructure_upgrade_insufficient_budget', ['cost' => Money::format($cost)])
            );
        }

        return DB::transaction(function () use ($game, $investment, $area, $targetTier, $newAmount, $cost, $currentTier) {
            $investment->update([
                "{$area}_amount" => $newAmount,
                "{$area}_tier" => $targetTier,
                'transfer_budget' => $investment->transfer_budget - $cost,
            ]);

            FinancialTransaction::recordExpense(
                gameId: $game->id,
                category: FinancialTransaction::CATEGORY_INFRASTRUCTURE,
                amount: $cost,
                description: __('finances.tx_infrastructure_upgrade', [
                    'area' => __("finances.{$area}"),
                    'from' => $currentTier,
                    'to' => $targetTier,
                ]),
                transactionDate: $game->current_date,
            );

            return $investment->fresh();
        });
    }
}
