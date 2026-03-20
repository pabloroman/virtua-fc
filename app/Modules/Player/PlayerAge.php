<?php

namespace App\Modules\Player;

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
    public const ACADEMY_END = 20;        // 21+ must leave academy

    // Development categories
    public const YOUNG_END = 23;          // Growing phase ends
    public const PRIME_END = 34;          // Peak phase ends; PRIME_END + 1 = veteran

    // Retirement boundaries
    public const MIN_RETIREMENT_OUTFIELD = 33;
    public const MIN_RETIREMENT_GK = 35;
    public const MAX_CAREER_OUTFIELD = 40;
    public const MAX_CAREER_GK = 42;

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
