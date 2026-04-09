<?php

namespace App\Modules\Player\Services;

/**
 * Static configuration for player development curves.
 *
 * Uses direct signed change values per age instead of multipliers.
 * Positive = growth, zero = plateau, negative = decline.
 * Growth requires playing time — no appearances means no growth (stagnation).
 */
final class DevelopmentCurve
{
    /**
     * Minimum appearances to qualify for any growth at all.
     * Below this threshold, positive development is zero (stagnation).
     */
    public const MIN_APPEARANCES_FOR_GROWTH = 10;

    /**
     * Appearances needed for full growth rate.
     * Between MIN and this value, growth scales linearly.
     */
    public const FULL_BONUS_APPEARANCES = 25;

    /**
     * Age-based development changes (points per season for a regular starter).
     *
     * - 16-19: Growth phase (young players improve if they play)
     * - 20-21: Late development (smaller gains)
     * - 22-24: Plateau (no growth, no decline — peak maintenance)
     * - 25-26: Physical decline begins, technical holds
     * - 27+: Both abilities decline, physical faster than technical
     */
    public const AGE_CURVES = [
        16 => ['technical' => 3, 'physical' => 3],
        17 => ['technical' => 2, 'physical' => 3],
        18 => ['technical' => 2, 'physical' => 2],
        19 => ['technical' => 2, 'physical' => 2],
        20 => ['technical' => 1, 'physical' => 1],
        21 => ['technical' => 1, 'physical' => 1],
        22 => ['technical' => 1, 'physical' => 0],
        23 => ['technical' => 0, 'physical' => 0],
        24 => ['technical' => 0, 'physical' => 0],
        25 => ['technical' => 0, 'physical' => -1],
        26 => ['technical' => 0, 'physical' => -1],
        27 => ['technical' => -1, 'physical' => -1],
        28 => ['technical' => -1, 'physical' => -2],
        29 => ['technical' => -1, 'physical' => -2],
        30 => ['technical' => -2, 'physical' => -3],
        31 => ['technical' => -2, 'physical' => -3],
        32 => ['technical' => -3, 'physical' => -4],
        33 => ['technical' => -3, 'physical' => -4],
        34 => ['technical' => -4, 'physical' => -5],
    ];

    /**
     * Get the development changes for a given age.
     *
     * @return array{technical: int, physical: int}
     */
    public static function getChanges(int $age): array
    {
        // Clamp age to our defined range
        if ($age < 16) {
            return self::AGE_CURVES[16];
        }

        if ($age > 34) {
            return self::AGE_CURVES[34];
        }

        return self::AGE_CURVES[$age];
    }

    /**
     * Calculate development change for a single ability.
     *
     * Growth (positive baseChange) requires playing time:
     * - Below MIN_APPEARANCES_FOR_GROWTH: zero growth (stagnation)
     * - Between MIN and FULL_BONUS: scaled growth
     * - At FULL_BONUS+: full growth
     *
     * Decline (negative baseChange) happens regardless, but active players decline slower.
     *
     * @param int $baseChange The age-based change for this ability (from AGE_CURVES)
     * @param int $seasonAppearances Number of appearances this season
     * @return int The final change in ability points
     */
    public static function calculateChange(int $baseChange, int $seasonAppearances): int
    {
        if ($baseChange > 0) {
            // Growth requires playing time — no play = no growth
            if ($seasonAppearances < self::MIN_APPEARANCES_FOR_GROWTH) {
                return 0;
            }

            $playFactor = min(1.0, $seasonAppearances / self::FULL_BONUS_APPEARANCES);

            return (int) round($baseChange * $playFactor);
        }

        if ($baseChange < 0) {
            // Decline happens regardless, but active players decline at half rate
            if ($seasonAppearances >= self::MIN_APPEARANCES_FOR_GROWTH) {
                return (int) round($baseChange * 0.5);
            }

            return $baseChange;
        }

        return 0;
    }
}
