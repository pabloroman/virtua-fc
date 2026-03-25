<?php

namespace App\Modules\Player\Services;

use App\Modules\Player\PlayerAge;

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
    private const ABILITY_VALUE_ANCHORS = [
        [45, 10_000_000],        // €100K
        [50, 30_000_000],        // €300K
        [58, 100_000_000],       // €1M
        [63, 200_000_000],       // €2M
        [68, 500_000_000],       // €5M
        [73, 1_000_000_000],     // €10M
        [78, 2_000_000_000],     // €20M
        [83, 5_000_000_000],     // €50M
        [88, 10_000_000_000],    // €100M
        [95, 20_000_000_000],    // €200M
    ];

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

        if ($age > PlayerAge::PRIME_END) {
            $physical = (int) round($physical * 0.90);
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
        // Deterministic base value via log-linear interpolation of forward mapping anchors
        $baseValue = $this->abilityToBaseValue($averageAbility);

        // Age multiplier
        $ageMultiplier = match (true) {
            $age <= 19 => 1.8,
            $age <= 21 => 1.5,
            $age <= 23 => 1.3,
            $age <= 26 => 1.1,
            $age <= 31 => 1.0,
            $age <= 33 => 0.75,
            $age <= 35 => 0.45,
            $age <= 37 => 0.30,
            default => 0.15,
        };

        // Performance trend multiplier (only during season-end)
        $trendMultiplier = 1.0;
        if ($previousAbility !== null) {
            $change = $averageAbility - $previousAbility;

            if ($age <= PlayerAge::YOUNG_END && $change > 0) {
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
     * Convert market value to a raw ability score via log-linear interpolation.
     *
     * Uses the same anchor points as abilityToBaseValue() but in reverse,
     * making the forward and reverse mappings near-symmetric.
     * Small random variance (±1) prevents identical scores for similar values.
     */
    private function marketValueToRawAbility(int $marketValueCents): int
    {
        $anchors = self::ABILITY_VALUE_ANCHORS;

        if ($marketValueCents <= $anchors[0][1]) {
            return max(40, $anchors[0][0] + rand(-2, 2));
        }

        $last = count($anchors) - 1;
        if ($marketValueCents >= $anchors[$last][1]) {
            return min(99, $anchors[$last][0] + rand(-1, 2));
        }

        for ($i = 0; $i < $last; $i++) {
            [$aLow, $vLow] = $anchors[$i];
            [$aHigh, $vHigh] = $anchors[$i + 1];

            if ($marketValueCents >= $vLow && $marketValueCents <= $vHigh) {
                $t = (log($marketValueCents) - log($vLow)) / (log($vHigh) - log($vLow));
                $ability = $aLow + $t * ($aHigh - $aLow);

                return max(40, min(99, (int) round($ability) + rand(-1, 1)));
            }
        }

        return $anchors[0][0]; // @codeCoverageIgnore — unreachable, anchors are contiguous
    }

    /**
     * Adjust raw ability for age (young players capped, veterans boosted).
     *
     * Young players with exceptional market value get higher caps (proven talent).
     * Veterans with high market value get ability boosts (proven quality).
     */
    private function adjustAbilityForAge(int $rawAbility, int $marketValueCents, int $age): int
    {
        if ($age < PlayerAge::YOUNG_END) {
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

        // Boost starts 3 years before official veteran age.
        if ($age <= PlayerAge::PRIME_END - 3) {
            return $rawAbility;
        }

        // Veterans: boost ability if market value proves they're still elite
        $typicalValueForAge = match (true) {
            $age <= 33 => 500_000_000,   // €5M
            $age <= 35 => 300_000_000,   // €3M
            $age <= 37 => 150_000_000,   // €1.5M
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

    /**
     * Deterministic ability-to-market-value mapping via log-linear interpolation.
     *
     * Anchor points are derived from the forward mapping tier boundaries
     * in marketValueToRawAbility(), making this the mathematical inverse.
     * Interpolation in log-space produces smooth exponential growth between anchors.
     *
     * @param int $ability Average ability (tech + phys) / 2
     * @return int Market value in cents
     */
    private function abilityToBaseValue(int $ability): int
    {
        $anchors = self::ABILITY_VALUE_ANCHORS;

        if ($ability <= $anchors[0][0]) {
            return $anchors[0][1];
        }

        $last = count($anchors) - 1;
        if ($ability >= $anchors[$last][0]) {
            return $anchors[$last][1];
        }

        for ($i = 0; $i < $last; $i++) {
            [$aLow, $vLow] = $anchors[$i];
            [$aHigh, $vHigh] = $anchors[$i + 1];

            if ($ability >= $aLow && $ability <= $aHigh) {
                $t = ($ability - $aLow) / ($aHigh - $aLow);
                $logValue = log($vLow) + $t * (log($vHigh) - log($vLow));

                return (int) round(exp($logValue));
            }
        }

        return $anchors[0][1]; // @codeCoverageIgnore — unreachable, anchors are contiguous
    }
}
