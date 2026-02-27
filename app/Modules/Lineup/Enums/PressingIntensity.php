<?php

namespace App\Modules\Lineup\Enums;

enum PressingIntensity: string
{
    case HIGH_PRESS = 'high_press';
    case STANDARD = 'standard';
    case LOW_BLOCK = 'low_block';

    public function label(): string
    {
        return match ($this) {
            self::HIGH_PRESS => __('game.pressing_high_press'),
            self::STANDARD => __('game.pressing_standard'),
            self::LOW_BLOCK => __('game.pressing_low_block'),
        };
    }

    public function tooltip(): string
    {
        return match ($this) {
            self::HIGH_PRESS => __('game.pressing_tip_high_press'),
            self::STANDARD => __('game.pressing_tip_standard'),
            self::LOW_BLOCK => __('game.pressing_tip_low_block'),
        };
    }

    public function summary(): string
    {
        return match ($this) {
            self::HIGH_PRESS => __('game.pressing_summary_high_press'),
            self::STANDARD => __('game.pressing_summary_standard'),
            self::LOW_BLOCK => __('game.pressing_summary_low_block'),
        };
    }

    /**
     * Multiplier on YOUR expected goals.
     */
    public function ownXGModifier(): float
    {
        return (float) config("match_simulation.pressing.{$this->value}.own_xg", 1.00);
    }

    /**
     * Multiplier on OPPONENT's expected goals against you.
     * High Press fades linearly after a configured minute.
     *
     * @param int $effectiveMinute The midpoint minute for this simulation segment
     */
    public function opponentXGModifier(int $effectiveMinute = 0): float
    {
        $base = (float) config("match_simulation.pressing.{$this->value}.opp_xg", 1.00);
        $fadeAfter = config("match_simulation.pressing.{$this->value}.fade_after");

        if ($fadeAfter === null || $effectiveMinute <= $fadeAfter) {
            return $base;
        }

        $fadeTo = (float) config("match_simulation.pressing.{$this->value}.fade_opp_xg", $base);
        $fadeRange = 90 - $fadeAfter;

        if ($fadeRange <= 0) {
            return $fadeTo;
        }

        $progress = min(1.0, ($effectiveMinute - $fadeAfter) / $fadeRange);

        return $base + ($fadeTo - $base) * $progress;
    }

    /**
     * Energy drain rate multiplier (> 1.0 = drains faster).
     */
    public function energyDrainMultiplier(): float
    {
        return (float) config("match_simulation.pressing.{$this->value}.energy_drain", 1.00);
    }
}
