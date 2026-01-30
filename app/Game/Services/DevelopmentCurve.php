<?php

namespace App\Game\Services;

/**
 * Static configuration for player development curves.
 *
 * Defines age-based development multipliers and match experience bonuses.
 * Values > 1.0 indicate growth, values < 1.0 indicate decline.
 */
final class DevelopmentCurve
{
    /**
     * Base development points per season (before multipliers).
     */
    public const BASE_DEVELOPMENT = 2;

    /**
     * Minimum appearances in a season to qualify for the starter bonus.
     */
    public const MIN_APPEARANCES_FOR_BONUS = 15;

    /**
     * Multiplier for players who meet the appearance threshold.
     * Represents +50% development bonus for regular starters.
     */
    public const APPEARANCE_BONUS = 1.5;

    /**
     * Age-based development multipliers for technical and physical abilities.
     *
     * - 16-20: High growth (multipliers > 1.2)
     * - 21-24: Moderate growth (multipliers 1.0-1.1)
     * - 25-28: Peak years (stable, technical ~1.0, physical starts declining)
     * - 29-31: Early decline (both abilities declining)
     * - 32+: Significant decline (physical declines faster than technical)
     */
    public const AGE_CURVES = [
        16 => ['technical' => 1.8, 'physical' => 2.0],
        17 => ['technical' => 1.6, 'physical' => 1.8],
        18 => ['technical' => 1.4, 'physical' => 1.5],
        19 => ['technical' => 1.3, 'physical' => 1.3],
        20 => ['technical' => 1.2, 'physical' => 1.2],
        21 => ['technical' => 1.1, 'physical' => 1.1],
        22 => ['technical' => 1.05, 'physical' => 1.0],
        23 => ['technical' => 1.0, 'physical' => 1.0],
        24 => ['technical' => 1.0, 'physical' => 1.0],
        25 => ['technical' => 1.0, 'physical' => 0.95],
        26 => ['technical' => 1.0, 'physical' => 0.9],
        27 => ['technical' => 0.95, 'physical' => 0.85],
        28 => ['technical' => 0.9, 'physical' => 0.8],
        29 => ['technical' => 0.85, 'physical' => 0.7],
        30 => ['technical' => 0.8, 'physical' => 0.6],
        31 => ['technical' => 0.7, 'physical' => 0.5],
        32 => ['technical' => 0.6, 'physical' => 0.4],
        33 => ['technical' => 0.5, 'physical' => 0.3],
        34 => ['technical' => 0.4, 'physical' => 0.2],
    ];

    /**
     * Get the development multipliers for a given age.
     *
     * @return array{technical: float, physical: float}
     */
    public static function getMultipliers(int $age): array
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
     * @param float $multiplier The age-based multiplier for this ability
     * @param bool $hasAppearanceBonus Whether the player qualifies for the starter bonus
     * @return int The change in ability points (can be negative for decline)
     */
    public static function calculateChange(float $multiplier, bool $hasAppearanceBonus): int
    {
        // Base development adjusted by age multiplier
        $development = self::BASE_DEVELOPMENT * $multiplier;

        // Apply appearance bonus for players who play regularly
        if ($hasAppearanceBonus && $multiplier > 0) {
            $development *= self::APPEARANCE_BONUS;
        }

        // For declining players (multiplier < 1), convert to negative change
        // e.g., multiplier 0.6 means they develop at 60% rate, but since they're
        // past peak, this becomes decline
        if ($multiplier < 1.0) {
            // The further below 1.0, the more they decline
            // 0.8 = -0.4 points, 0.5 = -1.0 points, 0.2 = -1.6 points
            $declineRate = (1.0 - $multiplier) * self::BASE_DEVELOPMENT;
            return (int) round(-$declineRate);
        }

        return (int) round($development);
    }

    /**
     * Check if the player qualifies for the appearance bonus.
     */
    public static function qualifiesForBonus(int $seasonAppearances): bool
    {
        return $seasonAppearances >= self::MIN_APPEARANCES_FOR_BONUS;
    }

    /**
     * Get development status label based on age.
     */
    public static function getStatus(int $age): string
    {
        if ($age <= 23) {
            return 'growing';
        }

        if ($age <= 28) {
            return 'peak';
        }

        return 'declining';
    }
}
