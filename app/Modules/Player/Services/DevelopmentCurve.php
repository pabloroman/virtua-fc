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
     * - 16-18: Strongest growth (young players improve fastest if they play)
     * - 19-22: Steady growth (developing into prime form)
     * - 23-25: Late development (smaller gains, still climbing)
     * - 26-27: Plateau (no growth, no decline — peak maintenance)
     * - 28-29: Mild decline begins
     * - 30+: Decline accelerates with age
     */
    public const AGE_CURVES = [
        16 => 3,
        17 => 3,
        18 => 3,
        19 => 2,
        20 => 2,
        21 => 2,
        22 => 2,
        23 => 1,
        24 => 1,
        25 => 1,
        26 => 0,
        27 => 0,
        28 => -1,
        29 => -2,
        30 => -2,
        31 => -3,
        32 => -3,
        33 => -4,
        34 => -5,
    ];

    /**
     * Last age where a player can still receive growth from the curve and the
     * quality-gap bonus. Plateau begins immediately after this age.
     */
    public const GROWTH_WINDOW_END_AGE = 25;

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

    /**
     * Quality-gap bonus for young players still climbing toward their potential.
     *
     * Tiered by gap size so prospects with more headroom develop faster,
     * partially counteracting the curve's diminishing-returns shape.
     * Only applies during the growth window (age <= GROWTH_WINDOW_END_AGE).
     */
    public static function gapBonus(int $age, int $currentAbility, int $potential): int
    {
        if ($age > self::GROWTH_WINDOW_END_AGE) {
            return 0;
        }

        $gap = $potential - $currentAbility;

        if ($gap >= 25) {
            return 2;
        }

        if ($gap >= 15) {
            return 1;
        }

        return 0;
    }

    /**
     * Upper bound on the lifetime growth a player can earn from this age
     * onward, assuming full playtime and a sustained max-tier gap bonus.
     *
     * Used to clamp generated potential so the displayed ceiling is actually
     * reachable. Without this clamp the curve can deliver at most ~40 points
     * of growth to a 16yo, but generatePotential could otherwise emit ceilings
     * 50+ points above current ability.
     */
    public static function maxLifetimeGrowth(int $age): int
    {
        if ($age > self::GROWTH_WINDOW_END_AGE) {
            return 0;
        }

        $effectiveAge = max(16, $age);

        return self::baseGrowthFromAge($effectiveAge)
            + (self::GROWTH_WINDOW_END_AGE - $effectiveAge + 1) * 2;
    }

    /**
     * Sum of positive AGE_CURVES entries from $age through GROWTH_WINDOW_END_AGE.
     */
    private static function baseGrowthFromAge(int $age): int
    {
        $sum = 0;
        for ($a = max(16, $age); $a <= self::GROWTH_WINDOW_END_AGE; $a++) {
            $sum += max(0, self::AGE_CURVES[$a] ?? 0);
        }

        return $sum;
    }
}
