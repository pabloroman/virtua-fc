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
     * @return array{finances: \App\Models\GameFinances, investment: ?GameInvestment, availableSurplus: int, tiers: array, reputationLevel: string, tierThresholds: array, minimumTier: int}
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
        $competitionTier = (int) ($game->competition->tier ?? 1);
        $minimumTier = GameInvestment::minimumTierForCompetitionTier($competitionTier);

        if ($investment) {
            $tiers = [
                'youth_academy' => $investment->youth_academy_tier,
                'medical' => $investment->medical_tier,
                'scouting' => $investment->scouting_tier,
                'facilities' => $investment->facilities_tier,
            ];
        } elseif ($previousInvestment) {
            // When promoting out of Primera RFEF, last season's tier-0 picks would
            // be below the new division's minimum tier — clamp up so the preselected
            // tiers don't render below the slider floor.
            $tiers = [
                'youth_academy' => max($minimumTier, $previousInvestment->youth_academy_tier),
                'medical' => max($minimumTier, $previousInvestment->medical_tier),
                'scouting' => max($minimumTier, $previousInvestment->scouting_tier),
                'facilities' => max($minimumTier, $previousInvestment->facilities_tier),
            ];
        } else {
            $tiers = GameInvestment::defaultTiersForReputation($reputationLevel, $availableSurplus, $minimumTier);
        }

        return [
            'finances' => $finances,
            'investment' => $investment,
            'availableSurplus' => $availableSurplus,
            'tiers' => $tiers,
            'reputationLevel' => $reputationLevel,
            'tierThresholds' => GameInvestment::thresholdsForCompetitionTier($competitionTier),
            'minimumTier' => $minimumTier,
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

        $competitionTier = (int) ($game->competition->tier ?? 1);
        $minimumAmounts = GameInvestment::minimumAmountsForCompetitionTier($competitionTier);

        if (
            $youthAcademy < $minimumAmounts['youth_academy']
            || $medical < $minimumAmounts['medical']
            || $scouting < $minimumAmounts['scouting']
            || $facilities < $minimumAmounts['facilities']
        ) {
            throw new \InvalidArgumentException('messages.budget_minimum_tier');
        }

        $youthTier = GameInvestment::calculateTier('youth_academy', $youthAcademy);
        $medicalTier = GameInvestment::calculateTier('medical', $medical);
        $scoutingTier = GameInvestment::calculateTier('scouting', $scouting);
        $facilitiesTier = GameInvestment::calculateTier('facilities', $facilities);

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
