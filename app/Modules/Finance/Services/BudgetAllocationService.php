<?php

namespace App\Modules\Finance\Services;

use App\Models\Game;
use App\Models\GameInvestment;

class BudgetAllocationService
{
    /**
     * Allocate budget from validated euro amounts.
     *
     * @param  array<string, numeric-string>  $amountsInEuros  Keys: youth_academy, medical, scouting, facilities, transfer_budget
     *
     * @throws \InvalidArgumentException
     */
    public function allocate(Game $game, array $amountsInEuros): GameInvestment
    {
        $availableSurplus = $game->currentFinances->available_surplus;

        // Convert from euros to cents, round to avoid floating point issues
        $youthAcademy = (int) round($amountsInEuros['youth_academy'] * 100);
        $medical = (int) round($amountsInEuros['medical'] * 100);
        $scouting = (int) round($amountsInEuros['scouting'] * 100);
        $facilities = (int) round($amountsInEuros['facilities'] * 100);
        $transferBudget = (int) round($amountsInEuros['transfer_budget'] * 100);

        $total = $youthAcademy + $medical + $scouting + $facilities + $transferBudget;

        if ($total > $availableSurplus) {
            throw new \InvalidArgumentException('messages.budget_exceeds_surplus');
        }

        $youthTier = GameInvestment::calculateTier('youth_academy', $youthAcademy);
        $medicalTier = GameInvestment::calculateTier('medical', $medical);
        $scoutingTier = GameInvestment::calculateTier('scouting', $scouting);
        $facilitiesTier = GameInvestment::calculateTier('facilities', $facilities);

        if ($youthTier < 1 || $medicalTier < 1 || $scoutingTier < 1 || $facilitiesTier < 1) {
            throw new \InvalidArgumentException('messages.budget_minimum_tier');
        }

        return GameInvestment::updateOrCreate(
            [
                'game_id' => $game->id,
                'season' => $game->season,
            ],
            [
                'available_surplus' => $availableSurplus,
                'youth_academy_amount' => $youthAcademy,
                'youth_academy_tier' => $youthTier,
                'medical_amount' => $medical,
                'medical_tier' => $medicalTier,
                'scouting_amount' => $scouting,
                'scouting_tier' => $scoutingTier,
                'facilities_amount' => $facilities,
                'facilities_tier' => $facilitiesTier,
                'transfer_budget' => $transferBudget,
            ]
        );
    }
}
