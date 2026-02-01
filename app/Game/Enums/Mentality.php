<?php

namespace App\Game\Enums;

enum Mentality: string
{
    case DEFENSIVE = 'defensive';
    case BALANCED = 'balanced';
    case ATTACKING = 'attacking';

    /**
     * Get a human-readable label for the mentality.
     */
    public function label(): string
    {
        return match ($this) {
            self::DEFENSIVE => 'Defensive',
            self::BALANCED => 'Balanced',
            self::ATTACKING => 'Attacking',
        };
    }

    /**
     * Modifier applied to YOUR team's expected goals.
     */
    public function ownGoalsModifier(): float
    {
        return match ($this) {
            self::DEFENSIVE => 0.70,  // -30% your goals
            self::BALANCED => 1.00,   // No change
            self::ATTACKING => 1.25,  // +25% your goals
        };
    }

    /**
     * Modifier applied to OPPONENT's expected goals against you.
     */
    public function opponentGoalsModifier(): float
    {
        return match ($this) {
            self::DEFENSIVE => 0.60,  // -40% opponent's goals (you defend well)
            self::BALANCED => 1.00,   // No change
            self::ATTACKING => 1.15,  // +15% opponent's goals (you leave gaps)
        };
    }
}
