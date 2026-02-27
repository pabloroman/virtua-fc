<?php

namespace App\Modules\Lineup\Enums;

enum DefensiveLineHeight: string
{
    case HIGH_LINE = 'high_line';
    case NORMAL = 'normal';
    case DEEP = 'deep';

    public function label(): string
    {
        return match ($this) {
            self::HIGH_LINE => __('game.defline_high_line'),
            self::NORMAL => __('game.defline_normal'),
            self::DEEP => __('game.defline_deep'),
        };
    }

    public function tooltip(): string
    {
        return match ($this) {
            self::HIGH_LINE => __('game.defline_tip_high_line'),
            self::NORMAL => __('game.defline_tip_normal'),
            self::DEEP => __('game.defline_tip_deep'),
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::HIGH_LINE => __('game.defline_summary_high_line'),
            self::NORMAL => __('game.defline_summary_normal'),
            self::DEEP => __('game.defline_summary_deep'),
        };
    }

    /**
     * Multiplier on YOUR expected goals.
     */
    public function ownXGModifier(): float
    {
        return (float) config("match_simulation.defensive_line.{$this->value}.own_xg", 1.00);
    }

    /**
     * Multiplier on OPPONENT's expected goals against you.
     */
    public function opponentXGModifier(): float
    {
        return (float) config("match_simulation.defensive_line.{$this->value}.opp_xg", 1.00);
    }

    /**
     * Physical ability threshold above which opponent forwards nullify the high line.
     * Returns 0 if no threshold applies.
     */
    public function physicalThreshold(): int
    {
        return (int) config("match_simulation.defensive_line.{$this->value}.physical_threshold", 0);
    }
}
