<?php

namespace App\Modules\Player\Services;

/**
 * Static configuration for player development curves.
 *
 * Uses direct signed change values per age instead of multipliers.
 * Positive = growth, zero = plateau, negative = decline.
 * Match playing time accelerates growth, but training alone still develops
 * young players at a reduced rate.
 */
final class DevelopmentCurve
{
    /**
     * Minimum appearances to qualify for full match-driven growth.
     * Below this threshold, players still develop from training but at a
     * reduced rate (TRAINING_ONLY_GROWTH_FACTOR).
     */
    public const MIN_APPEARANCES_FOR_GROWTH = 10;

    /**
     * Appearances needed for full growth rate.
     * Between MIN and this value, growth scales linearly.
     */
    public const FULL_BONUS_APPEARANCES = 25;

    /**
     * Fraction of base growth applied to bench players (no/few appearances).
     * Models off-pitch training development. Without this, AI bench prospects
     * stagnate forever once squads fill up, eroding league strength over time.
     */
    public const TRAINING_ONLY_GROWTH_FACTOR = 0.5;

    /**
     * Age-based development changes (points per season for a regular starter).
     *
     * Single-axis arc — average of the legacy technical/physical curves so
     * the headline trajectory (growth → plateau → decline) is preserved
     * after the flatten to a single overall_score column.
     *
     * - 16-19: Growth phase (young players improve if they play)
     * - 20-21: Late development (smaller gains)
     * - 22-24: Plateau (no growth, no decline — peak maintenance)
     * - 25-26: Mild decline begins
     * - 27+: Decline accelerates with age
     */
    public const AGE_CURVES = [
        16 => 3,
        17 => 3,
        18 => 2,
        19 => 2,
        20 => 1,
        21 => 1,
        22 => 1,
        23 => 0,
        24 => 0,
        25 => -1,
        26 => -1,
        27 => -1,
        28 => -2,
        29 => -2,
        30 => -3,
        31 => -3,
        32 => -4,
        33 => -4,
        34 => -5,
    ];

    /**
     * Get the development change for a given age.
     */
    public static function getChange(int $age): int
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
     * Calculate development change for the overall ability.
     *
     * Growth (positive baseChange) is driven by playing time:
     * - Below MIN_APPEARANCES_FOR_GROWTH: training-only rate (TRAINING_ONLY_GROWTH_FACTOR)
     * - Between MIN and FULL_BONUS: scaled growth
     * - At FULL_BONUS+: full growth
     *
     * Decline (negative baseChange) happens regardless, but active players decline slower.
     *
     * @param int $baseChange The age-based change (from AGE_CURVES)
     * @param int $seasonAppearances Number of appearances this season
     * @return int The final change in ability points
     */
    public static function calculateChange(int $baseChange, int $seasonAppearances): int
    {
        if ($baseChange > 0) {
            if ($seasonAppearances < self::MIN_APPEARANCES_FOR_GROWTH) {
                return (int) round($baseChange * self::TRAINING_ONLY_GROWTH_FACTOR);
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
