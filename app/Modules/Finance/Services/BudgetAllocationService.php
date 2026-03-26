<?php

namespace App\Modules\Finance\Services;

use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\TeamReputation;

class BudgetAllocationService
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
    ) {}

    /**
     * Prepare budget allocation data for display (finances, tiers, minimums).
     *
     * @return array{finances: \App\Models\GameFinances, investment: ?GameInvestment, availableSurplus: int, tiers: array, minTiers: array, reputationLevel: string}
     */
    public function prepareBudgetData(Game $game): array
    {
        $finances = $game->currentFinances;
        if (!$finances) {
            $finances = $this->projectionService->generateProjections($game);
        }

        $investment = $game->currentInvestment;
        $availableSurplus = $finances->available_surplus ?? 0;
        $reputationLevel = TeamReputation::resolveLevel($game->id, $game->team_id);
        $previousInvestment = $game->previousSeasonInvestment();

        if ($investment) {
            $tiers = [
                'youth_academy' => $investment->youth_academy_tier,
                'medical' => $investment->medical_tier,
                'scouting' => $investment->scouting_tier,
                'facilities' => $investment->facilities_tier,
            ];
        } elseif ($previousInvestment) {
            $tiers = [
                'youth_academy' => $previousInvestment->youth_academy_tier,
                'medical' => $previousInvestment->medical_tier,
                'scouting' => $previousInvestment->scouting_tier,
                'facilities' => $previousInvestment->facilities_tier,
            ];
        } else {
            $tiers = GameInvestment::defaultTiersForReputation($reputationLevel, $availableSurplus);
        }

        $minTiers = $previousInvestment ? [
            'youth_academy' => $previousInvestment->youth_academy_tier,
            'medical' => $previousInvestment->medical_tier,
            'scouting' => $previousInvestment->scouting_tier,
            'facilities' => $previousInvestment->facilities_tier,
        ] : ['youth_academy' => 1, 'medical' => 1, 'scouting' => 1, 'facilities' => 1];

        return [
            'finances' => $finances,
            'investment' => $investment,
            'availableSurplus' => $availableSurplus,
            'tiers' => $tiers,
            'minTiers' => $minTiers,
            'reputationLevel' => $reputationLevel,
        ];
    }

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

        // Infrastructure tiers are permanent — cannot drop below previous season's levels
        $previousInvestment = $game->previousSeasonInvestment();
        if ($previousInvestment) {
            if ($youthTier < $previousInvestment->youth_academy_tier
                || $medicalTier < $previousInvestment->medical_tier
                || $scoutingTier < $previousInvestment->scouting_tier
                || $facilitiesTier < $previousInvestment->facilities_tier
            ) {
                throw new \InvalidArgumentException('messages.budget_tier_below_previous');
            }
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
