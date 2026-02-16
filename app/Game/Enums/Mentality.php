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
            self::DEFENSIVE => __('game.mentality_defensive'),
            self::BALANCED => __('game.mentality_balanced'),
            self::ATTACKING => __('game.mentality_attacking'),
        };
    }

    public function tooltip(): string
    {
        return match ($this) {
            self::DEFENSIVE => __('game.mentality_tip_defensive'),
            self::BALANCED => __('game.mentality_tip_balanced'),
            self::ATTACKING => __('game.mentality_tip_attacking'),
        };
    }

    /**
     * Modifier applied to YOUR team's expected goals.
     */
    public function ownGoalsModifier(): float
    {
        return match ($this) {
            self::DEFENSIVE => 0.80,  // -20% your goals
            self::BALANCED => 1.00,   // No change
            self::ATTACKING => 1.15,  // +15% your goals
        };
    }

    /**
     * Modifier applied to OPPONENT's expected goals against you.
     */
    public function opponentGoalsModifier(): float
    {
        return match ($this) {
            self::DEFENSIVE => 0.70,  // -30% opponent's goals (you defend well)
            self::BALANCED => 1.00,   // No change
            self::ATTACKING => 1.10,  // +10% opponent's goals (you leave gaps)
        };
    }
}
