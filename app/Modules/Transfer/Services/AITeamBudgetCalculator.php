<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use Illuminate\Support\Collection;

/**
 * Stateless, pure-compute service for AI team financial calculations.
 *
 * All methods work entirely from in-memory collections — zero DB queries.
 * Used by AITransferMarketService and TransferService to make budget-aware
 * transfer decisions for AI teams.
 */
class AITeamBudgetCalculator
{
    /**
     * Compute virtual transfer budgets for all AI teams.
     *
     * @param  Collection  $teamRosters  teamId => Collection<GamePlayer>
     * @param  Collection  $reputationLevels  teamId => reputation level string
     * @param  Collection  $completedFinancials  teamId => ['spent' => int, 'earned' => int, 'sells' => int, 'buys' => int]
     * @param  string  $season  Current season identifier
     * @param  string  $window  'summer' or 'winter'
     * @return Collection  teamId => budget array
     */
    public function computeBudgets(
        Collection $teamRosters,
        Collection $reputationLevels,
        Collection $completedFinancials,
        string $season,
        string $window,
    ): Collection {
        $isSummer = $window === 'summer';
        $reinvestmentRate = (float) config('finances.ai_reinvestment_rate', 0.70);
        $budgetsByReputation = config('finances.ai_transfer_budgets', []);

        return $teamRosters->keys()->mapWithKeys(function ($teamId) use (
            $reputationLevels, $completedFinancials, $season, $window,
            $isSummer, $reinvestmentRate, $budgetsByReputation, $teamRosters,
        ) {
            $reputation = $reputationLevels->get($teamId, ClubProfile::REPUTATION_LOCAL);
            $spendingLimit = $budgetsByReputation[$reputation] ?? $budgetsByReputation['local'] ?? 300_000_000;

            // Scale by window: winter gets 40% of the summer envelope
            if (! $isSummer) {
                $spendingLimit = (int) ($spendingLimit * 0.40);
            }

            // Apply financial pressure: teams with high wage bills get reduced budgets
            $players = $teamRosters->get($teamId, collect());
            $pressure = $this->financialPressure($players, $reputation);
            $pressureDiscount = 1.0 - ($pressure * 0.50); // Up to 50% budget reduction at max pressure
            $spendingLimit = (int) ($spendingLimit * max(0.30, $pressureDiscount));

            $completed = $completedFinancials->get($teamId, ['spent' => 0, 'earned' => 0, 'sells' => 0, 'buys' => 0]);
            $maxTransfers = $this->computeTransferCount($teamId, $season, $window, $isSummer, $reputation);

            $available = $spendingLimit
                + (int) ($completed['earned'] * $reinvestmentRate)
                - $completed['spent'];

            return [$teamId => [
                'max' => $maxTransfers,
                'spending_limit' => $spendingLimit,
                'spent' => $completed['spent'],
                'earned' => $completed['earned'],
                'available' => max(0, $available),
                'sells' => $completed['sells'],
                'buys' => $completed['buys'],
            ]];
        });
    }

    /**
     * Compute financial pressure for a team (0.0-1.0).
     *
     * Based on wage-to-revenue ratio. Higher pressure = team is spending
     * unsustainably on wages relative to their estimated revenue.
     *
     * @param  Collection  $players  Team's roster (must include annual_wage)
     * @param  string  $reputationLevel  Team's reputation level
     */
    public function financialPressure(Collection $players, string $reputationLevel): float
    {
        $estimatedRevenue = $this->estimatedRevenue($reputationLevel);

        if ($estimatedRevenue <= 0) {
            return 1.0;
        }

        $totalWages = $players->sum('annual_wage');

        // Wages as a fraction of revenue. Healthy is ~30-50%, stressed is 60%+
        $ratio = $totalWages / $estimatedRevenue;

        // Normalize: 0.0 at ≤40% wage ratio, 1.0 at ≥80% wage ratio
        $pressure = ($ratio - 0.40) / 0.40;

        return max(0.0, min(1.0, $pressure));
    }

    /**
     * Estimate annual revenue for a team by reputation (in cents).
     */
    public function estimatedRevenue(string $reputationLevel): int
    {
        $revenues = config('finances.ai_estimated_revenue', []);

        return $revenues[$reputationLevel] ?? $revenues['local'] ?? 1_000_000_000;
    }

    /**
     * Compute a deterministic transfer count for a team using hash-based distribution.
     * Same inputs always produce the same count — no state storage needed.
     */
    private function computeTransferCount(
        string $teamId,
        string $season,
        string $window,
        bool $isSummer,
        string $reputationLevel,
    ): int {
        $configKey = $isSummer
            ? 'finances.ai_transfer_count_weights_summer'
            : 'finances.ai_transfer_count_weights_winter';

        $allWeights = config($configKey, []);
        $weights = $allWeights[$reputationLevel]
            ?? $allWeights['established']
            ?? ($isSummer ? [1 => 15, 2 => 30, 3 => 30, 4 => 15, 5 => 10] : [1 => 50, 2 => 35, 3 => 15]);

        $hash = crc32($teamId.$season.$window);
        $total = array_sum($weights);
        $roll = (($hash & 0x7FFFFFFF) % $total) + 1;
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
