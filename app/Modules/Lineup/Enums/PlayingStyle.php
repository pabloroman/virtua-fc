<?php

namespace App\Modules\Lineup\Enums;

enum PlayingStyle: string
{
    case POSSESSION = 'possession';
    case BALANCED = 'balanced';
    case COUNTER_ATTACK = 'counter_attack';
    case DIRECT = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::POSSESSION => __('game.style_possession'),
            self::BALANCED => __('game.style_balanced'),
            self::COUNTER_ATTACK => __('game.style_counter_attack'),
            self::DIRECT => __('game.style_direct'),
        };
    }

    public function tooltip(): string
    {
        return match ($this) {
            self::POSSESSION => __('game.style_tip_possession'),
            self::BALANCED => __('game.style_tip_balanced'),
            self::COUNTER_ATTACK => __('game.style_tip_counter_attack'),
            self::DIRECT => __('game.style_tip_direct'),
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::POSSESSION => __('game.style_summary_possession'),
            self::BALANCED => __('game.style_summary_balanced'),
            self::COUNTER_ATTACK => __('game.style_summary_counter_attack'),
            self::DIRECT => __('game.style_summary_direct'),
        };
    }

    /**
     * Multiplier on YOUR expected goals.
     */
    public function ownXGModifier(): float
    {
        return (float) config("match_simulation.playing_styles.{$this->value}.own_xg", 1.00);
    }

    /**
     * Multiplier on OPPONENT's expected goals against you.
     */
    public function opponentXGModifier(): float
    {
        return (float) config("match_simulation.playing_styles.{$this->value}.opp_xg", 1.00);
    }

    /**
     * Energy drain rate multiplier (> 1.0 = drains faster).
     */
    public function energyDrainMultiplier(): float
    {
        return (float) config("match_simulation.playing_styles.{$this->value}.energy_drain", 1.00);
    }
}
