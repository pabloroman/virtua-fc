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
            // No investment row yet (defensive — the season-setup processor
            // normally creates one): start the sliders at the division floor.
            // Reputation-appropriate tiers are surfaced only as a recommendation.
            $tiers = [
                'youth_academy' => $minimumTier,
                'medical' => $minimumTier,
                'scouting' => $minimumTier,
                'facilities' => $minimumTier,
            ];
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

    /**
     * Apply the season's default investment allocation so a GameInvestment row —
     * and therefore a transfer budget — always exists once season setup finishes.
     * This is what lets the new-season screen stop being a blocking budget gate:
     * the board sets a sane starting plan automatically, and the manager refines
     * it later, reversibly, on the Club investment page.
     *
     * Idempotent: if the current season already has an investment row (re-entrant
     * setup job, or the manager already adjusted their plan) it is left untouched.
     */
    public function applyDefaultAllocation(Game $game): GameInvestment
    {
        if ($existing = $game->currentInvestment) {
            return $existing;
        }

        $finances = $game->currentFinances ?? $this->projectionService->generateProjections($game);
        $availableSurplus = $finances->available_surplus ?? 0;

        $competitionTier = (int) ($game->competition->tier ?? 1);
        $minimumTier = GameInvestment::minimumTierForCompetitionTier($competitionTier);
        $previousInvestment = $game->previousSeasonInvestment();

        if ($previousInvestment) {
            // Carry last season's picks forward (clamped to the new division's
            // floor — a promoted Primera RFEF side can't keep tier-0 picks).
            $tiers = [
                'youth_academy' => max($minimumTier, $previousInvestment->youth_academy_tier),
                'medical' => max($minimumTier, $previousInvestment->medical_tier),
                'scouting' => max($minimumTier, $previousInvestment->scouting_tier),
                'facilities' => max($minimumTier, $previousInvestment->facilities_tier),
            ];

            // Realise any downgrade the manager staged mid-season. A reduction
            // never clawed back committed spend; it lands here, as next season's
            // starting point.
            foreach (($previousInvestment->staged_downgrades ?? []) as $area => $tier) {
                if (array_key_exists($area, $tiers)) {
                    $tiers[$area] = max($minimumTier, (int) $tier);
                }
            }

            // Revenue can fall (relegation), so last season's spend may no longer
            // fit. Trim to what this season's surplus can carry so the auto-applied
            // plan never produces a negative transfer budget.
            $tiers = GameInvestment::trimTiersToBudget($tiers, $availableSurplus, $minimumTier);
        } else {
            // Brand-new club (season 1): start at the division's minimum tier so
            // the surplus lands in the transfer budget by default. The manager
            // opts into infrastructure deliberately on the Club investment page,
            // where a reputation-based recommendation is offered. This respects
            // the reversibility model — upgrades can be made at any time at full
            // cost, whereas a high starting plan would pre-commit (and lock up)
            // money that can't be reclaimed mid-season.
            $tiers = [
                'youth_academy' => $minimumTier,
                'medical' => $minimumTier,
                'scouting' => $minimumTier,
                'facilities' => $minimumTier,
            ];
        }

        return $this->persistTiers($game, $tiers, $availableSurplus, $competitionTier);
    }

    /**
     * Persist a tier selection: derive per-area amounts from the competition's
     * threshold table, set the transfer budget to whatever surplus remains, and
     * upsert the current season's investment row.
     *
     * @param  array<string, int>  $tiers
     */
    private function persistTiers(Game $game, array $tiers, int $availableSurplus, int $competitionTier): GameInvestment
    {
        $thresholds = GameInvestment::thresholdsForCompetitionTier($competitionTier);

        $amounts = [];
        foreach (['youth_academy', 'medical', 'scouting', 'facilities'] as $area) {
            $amounts[$area] = $thresholds[$area][$tiers[$area]];
        }

        $transferBudget = max(0, $availableSurplus - array_sum($amounts));

        return GameInvestment::updateOrCreate(
            ['game_id' => $game->id, 'season' => $game->season],
            [
                'available_surplus' => $availableSurplus,
                'youth_academy_amount' => $amounts['youth_academy'],
                'youth_academy_tier' => $tiers['youth_academy'],
                'medical_amount' => $amounts['medical'],
                'medical_tier' => $tiers['medical'],
                'scouting_amount' => $amounts['scouting'],
                'scouting_tier' => $tiers['scouting'],
                'facilities_amount' => $amounts['facilities'],
                'facilities_tier' => $tiers['facilities'],
                'transfer_budget' => $transferBudget,
            ],
        );
    }
}
