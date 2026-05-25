<?php

namespace App\Modules\Lineup;

/**
 * Controls how aggressively the assistant coach rotates tired players
 * out of the starting XI during automated lineup selection (fast mode).
 *
 * Each case tunes:
 *  - threshold(): the fitness value at/above which a player gets no
 *    fatigue penalty and is treated as "fresh" for head-to-head sub
 *    comparisons.
 *  - floor(): the score multiplier applied at fitness 0 (the maximum
 *    penalty cap). Lower floor → tired players are pushed further down
 *    the effective rating, so subs win the slot more easily.
 *
 * Conservative reproduces the historical tuning so callers that don't
 * specify a policy don't regress.
 */
enum RotationPolicy: string
{
    case Conservative = 'conservative';
    case Balanced = 'balanced';
    case Aggressive = 'aggressive';

    public function threshold(): int
    {
        return match ($this) {
            self::Conservative => 70,
            self::Balanced => 80,
            self::Aggressive => 90,
        };
    }

    public function floor(): float
    {
        return match ($this) {
            self::Conservative => 0.60,
            self::Balanced => 0.45,
            self::Aggressive => 0.30,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Conservative => 'game.fast_mode_rotation.conservative',
            self::Balanced => 'game.fast_mode_rotation.balanced',
            self::Aggressive => 'game.fast_mode_rotation.aggressive',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Conservative => 'game.fast_mode_rotation.conservative_desc',
            self::Balanced => 'game.fast_mode_rotation.balanced_desc',
            self::Aggressive => 'game.fast_mode_rotation.aggressive_desc',
        };
    }
}
