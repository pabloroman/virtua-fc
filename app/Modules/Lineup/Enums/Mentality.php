<?php

namespace App\Modules\Lineup\Enums;

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
        $ownPct = self::formatModifierPct($this->ownGoalsModifier());
        $oppPct = self::formatModifierPct($this->opponentGoalsModifier());

        $key = match ($this) {
            self::DEFENSIVE => 'game.mentality_tip_defensive',
            self::BALANCED => 'game.mentality_tip_balanced',
            self::ATTACKING => 'game.mentality_tip_attacking',
        };

        return __($key, ['own' => $ownPct, 'opponent' => $oppPct]);
    }

    private static function formatModifierPct(float $modifier): string
    {
        $pct = (int) round(($modifier - 1.0) * 100);

        return ($pct >= 0 ? '+' : '').$pct.'%';
    }

    /**
     * Modifier applied to YOUR team's expected goals.
     */
    public function ownGoalsModifier(): float
    {
        return (float) config("match_simulation.mentalities.{$this->value}.own_goals", 1.00);
    }

    /**
     * Modifier applied to OPPONENT's expected goals against you.
     */
    public function opponentGoalsModifier(): float
    {
        return (float) config("match_simulation.mentalities.{$this->value}.opponent_goals", 1.00);
    }
}
