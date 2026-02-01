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
     * Get a description of what this mentality does.
     */
    public function description(): string
    {
        return match ($this) {
            self::DEFENSIVE => 'Sit deep, focus on not conceding. Lower scoring games.',
            self::BALANCED => 'Standard approach. No modifiers.',
            self::ATTACKING => 'Push forward, take risks. Higher scoring games.',
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

    /**
     * Get the CSS color class for UI display.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::DEFENSIVE => 'text-blue-600 bg-blue-50 border-blue-200',
            self::BALANCED => 'text-slate-600 bg-slate-50 border-slate-200',
            self::ATTACKING => 'text-red-600 bg-red-50 border-red-200',
        };
    }

    /**
     * Get an icon/emoji for the mentality.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DEFENSIVE => '🛡️',
            self::BALANCED => '⚖️',
            self::ATTACKING => '⚔️',
        };
    }
}
