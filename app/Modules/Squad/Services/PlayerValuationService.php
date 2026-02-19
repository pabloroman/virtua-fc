<?php

namespace App\Modules\Squad\Services;

/**
 * Single source of truth for all ability <-> market value conversions.
 *
 * Consolidates logic previously fragmented across:
 * - SeedReferenceData (market value -> abilities during seeding)
 * - PlayerDevelopmentService::calculateMarketValue() (abilities -> market value)
 * - PlayerGeneratorService::estimateMarketValue() (abilities -> market value for generated players)
 *
 * Uses pure ability average (technical + physical) / 2 — NOT overall_score.
 * Fitness and morale are transient and must not permanently affect valuation.
 */
class PlayerValuationService
{
    /**
     * Convert market value to technical/physical abilities.
     *
     * Used during initial seeding to derive abilities from Transfermarkt data.
     *
     * @param int $marketValueCents Market value in cents (e.g., 1_500_000_000 = €15M)
     * @param string $position Player position (e.g., 'Centre-Forward', 'Goalkeeper')
     * @param int $age Player's current age
     * @return array{0: int, 1: int} [technical, physical]
     */
    public function marketValueToAbilities(int $marketValueCents, string $position, int $age): array
    {
        $rawAbility = $this->marketValueToRawAbility($marketValueCents);
        $baseAbility = $this->adjustAbilityForAge($rawAbility, $marketValueCents, $age);

        $technicalRatio = match ($position) {
            'Goalkeeper' => 0.55,
            'Centre-Back' => 0.35,
            'Left-Back', 'Right-Back' => 0.45,
            'Defensive Midfield' => 0.45,
            'Central Midfield' => 0.55,
            'Left Midfield', 'Right Midfield' => 0.55,
            'Attacking Midfield' => 0.70,
            'Left Winger', 'Right Winger' => 0.65,
            'Second Striker' => 0.70,
            'Centre-Forward' => 0.65,
            default => 0.50,
        };

        $variance = rand(2, 5);
        $technical = (int) round($baseAbility + ($technicalRatio - 0.5) * $variance * 2);
        $physical = (int) round($baseAbility + (0.5 - $technicalRatio) * $variance * 2);

        if ($age > 33) {
            $physical = (int) round($physical * 0.92);
        } elseif ($age > 30) {
            $physical = (int) round($physical * 0.96);
        }

        $technical = max(30, min(99, $technical));
        $physical = max(30, min(99, $physical));

        return [$technical, $physical];
    }

    /**
     * Convert abilities to market value.
     *
     * Used after season-end development, for generated players, etc.
     * Uses pure ability average (tech+phys)/2 — NOT overall_score.
     *
     * @param int $averageAbility (technical + physical) / 2
     * @param int $age Player's current age
     * @param int|null $previousAbility Previous season's average ability (for performance trend). Only passed during season-end.
     * @return int Market value in cents
     */
    public function abilityToMarketValue(int $averageAbility, int $age, ?int $previousAbility = null): int
    {
        // Base value from ability tier (unified with marketValueToRawAbility ranges)
        $baseValue = match (true) {
            $averageAbility >= 88 => mt_rand(60_000_000_00, 150_000_000_00),  // €60-150M
            $averageAbility >= 83 => mt_rand(35_000_000_00, 70_000_000_00),   // €35-70M
            $averageAbility >= 78 => mt_rand(20_000_000_00, 40_000_000_00),   // €20-40M
            $averageAbility >= 73 => mt_rand(10_000_000_00, 25_000_000_00),   // €10-25M
            $averageAbility >= 68 => mt_rand(5_000_000_00, 12_000_000_00),    // €5-12M
            $averageAbility >= 63 => mt_rand(2_000_000_00, 6_000_000_00),     // €2-6M
            $averageAbility >= 58 => mt_rand(1_000_000_00, 3_000_000_00),     // €1-3M
            $averageAbility >= 50 => mt_rand(500_000_00, 1_500_000_00),       // €500K-1.5M
            $averageAbility >= 45 => mt_rand(200_000_00, 600_000_00),         // €200K-600K
            default => mt_rand(100_000_00, 300_000_00),                        // €100-300K
        };

        // Age multiplier
        $ageMultiplier = match (true) {
            $age <= 19 => 1.8,
            $age <= 21 => 1.5,
            $age <= 23 => 1.3,
            $age <= 26 => 1.1,
            $age <= 28 => 1.0,
            $age <= 30 => 0.85,
            $age <= 32 => 0.65,
            $age <= 34 => 0.45,
            $age <= 36 => 0.30,
            default => 0.15,
        };

        // Performance trend multiplier (only during season-end)
        $trendMultiplier = 1.0;
        if ($previousAbility !== null) {
            $change = $averageAbility - $previousAbility;

            if ($age <= 24 && $change > 0) {
                // Young players who improve get bigger boost (confirming potential)
                $trendMultiplier = match (true) {
                    $change >= 5 => 1.4,
                    $change >= 3 => 1.25,
                    default => 1.1,
                };
            } elseif ($change < 0) {
                // Declining players lose value faster
                $trendMultiplier = match (true) {
                    $change <= -4 => 0.7,
                    $change <= -2 => 0.85,
                    default => 0.95,
                };
            }
        }

        $newValue = (int) round($baseValue * $ageMultiplier * $trendMultiplier);

        // Clamp to reasonable range: €100K to €200M
        return max(100_000_00, min(200_000_000_00, $newValue));
    }

    /**
     * Convert market value to a raw ability score.
     *
     * These tiers are the canonical mapping used bidirectionally:
     * - Forward: market value -> ability (seeding)
     * - Reverse: ability -> market value (abilityToMarketValue)
     */
    private function marketValueToRawAbility(int $marketValueCents): int
    {
        return match (true) {
            $marketValueCents >= 10_000_000_000 => rand(88, 95),  // €100M+
            $marketValueCents >= 5_000_000_000 => rand(83, 90),   // €50M+
            $marketValueCents >= 2_000_000_000 => rand(78, 85),   // €20M+
            $marketValueCents >= 1_000_000_000 => rand(73, 80),   // €10M+
            $marketValueCents >= 500_000_000 => rand(68, 75),     // €5M+
            $marketValueCents >= 200_000_000 => rand(63, 70),     // €2M+
            $marketValueCents >= 100_000_000 => rand(58, 65),     // €1M+
            $marketValueCents > 0 => rand(50, 60),                // Under €1M
            default => rand(45, 55),                               // Unknown
        };
    }

    /**
     * Adjust raw ability for age (young players capped, veterans boosted).
     *
     * Young players with exceptional market value get higher caps (proven talent).
     * Veterans with high market value get ability boosts (proven quality).
     */
    private function adjustAbilityForAge(int $rawAbility, int $marketValueCents, int $age): int
    {
        if ($age < 23) {
            // Base cap increases with age: 17yo = 75, 22yo = 85
            $ageCap = 75 + ($age - 17) * 2;

            // Exceptional market value raises the cap significantly
            if ($marketValueCents >= 15_000_000_000) {      // €150M+
                $ageCap += 14;
            } elseif ($marketValueCents >= 10_000_000_000) { // €100M+
                $ageCap += 10;
            } elseif ($marketValueCents >= 5_000_000_000) {  // €50M+
                $ageCap += 6;
            } elseif ($marketValueCents >= 2_000_000_000) {  // €20M+
                $ageCap += 3;
            }

            return min($rawAbility, $ageCap);
        }

        if ($age <= 30) {
            return $rawAbility;
        }

        // Veterans: boost ability if market value proves they're still elite
        $typicalValueForAge = match (true) {
            $age <= 32 => 500_000_000,   // €5M
            $age <= 34 => 300_000_000,   // €3M
            $age <= 36 => 150_000_000,   // €1.5M
            default => 80_000_000,        // €800K
        };

        $valueRatio = $marketValueCents / max(1, $typicalValueForAge);

        $abilityBoost = match (true) {
            $valueRatio >= 10 => 12,
            $valueRatio >= 5 => 8,
            $valueRatio >= 3 => 5,
            $valueRatio >= 2 => 3,
            $valueRatio >= 1 => 1,
            default => 0,
        };

        return min(95, $rawAbility + $abilityBoost);
    }
}
