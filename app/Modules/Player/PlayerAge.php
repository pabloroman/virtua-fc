<?php

namespace App\Modules\Player;

use Carbon\Carbon;

/**
 * Single source of truth for player age category boundaries.
 *
 * Only lifecycle boundaries belong here. Per-year lookup tables
 * (AGE_CURVES, AGE_WAGE_MODIFIERS, retirement probabilities)
 * and match simulation config stay in their respective locations.
 */
final class PlayerAge
{
    // Career boundaries
    public const ACADEMY_END = 21;        // 21+ must leave academy

    // Development categories
    public const YOUNG_END = 23;          // Growing phase ends
    public const PRIME_END = 34;          // Peak phase ends; PRIME_END + 1 = veteran

    // Retirement boundaries
    public const MIN_RETIREMENT_OUTFIELD = 33;
    public const MIN_RETIREMENT_GK = 35;
    public const MAX_CAREER_OUTFIELD = 40;
    public const MAX_CAREER_GK = 42;

    /**
     * Convert an age threshold to a date-of-birth cutoff for database queries.
     *
     * Players born on or before the returned date are at least $age years old.
     */
    public static function dateOfBirthCutoff(int $age, Carbon $referenceDate): Carbon
    {
        return $referenceDate->copy()->subYears($age);
    }

    /**
     * Linearly interpolate between YOUNG_END and PRIME_END.
     *
     * primePhaseAge(0.0) = YOUNG_END, primePhaseAge(1.0) = PRIME_END.
     */
    public static function primePhaseAge(float $t): int
    {
        return (int) round(self::YOUNG_END + $t * (self::PRIME_END - self::YOUNG_END));
    }

    /**
     * Is the player in the growing phase?
     */
    public static function isYoung(int $age): bool
    {
        return $age <= self::YOUNG_END;
    }

    /**
     * Is the player in the peak phase?
     */
    public static function isPrime(int $age): bool
    {
        return $age > self::YOUNG_END && $age <= self::PRIME_END;
    }

    /**
     * Is the player in the veteran/declining phase?
     */
    public static function isVeteran(int $age): bool
    {
        return $age > self::PRIME_END;
    }

    /**
     * Get development status label: 'growing', 'peak', or 'declining'.
     */
    public static function developmentStatus(int $age): string
    {
        if ($age <= self::YOUNG_END) {
            return 'growing';
        }

        if ($age <= self::PRIME_END) {
            return 'peak';
        }

        return 'declining';
    }
}
