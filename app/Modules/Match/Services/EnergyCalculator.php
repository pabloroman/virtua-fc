<?php

namespace App\Modules\Match\Services;

class EnergyCalculator
{
    /**
     * Calculate energy drain per minute for a player.
     *
     * @param  float  $tacticalDrainMultiplier  Combined tactical drain (playing style × pressing)
     */
    public static function drainPerMinute(int $physicalAbility, int $age, bool $isGoalkeeper, float $tacticalDrainMultiplier = 1.0): float
    {
        $baseDrain = config('match_simulation.energy.base_drain_per_minute', 0.75);
        $physicalFactor = config('match_simulation.energy.physical_ability_factor', 0.005);
        $ageThreshold = config('match_simulation.energy.age_threshold', 28);
        $agePenalty = config('match_simulation.energy.age_penalty_per_year', 0.015);
        $gkMultiplier = config('match_simulation.energy.gk_drain_multiplier', 0.5);

        $physicalBonus = ($physicalAbility - 50) * $physicalFactor;
        $ageExtra = max(0, $age - $ageThreshold) * $agePenalty;

        $drain = $baseDrain - $physicalBonus + $ageExtra;

        if ($isGoalkeeper) {
            $drain *= $gkMultiplier;
        }

        $drain *= $tacticalDrainMultiplier;

        return max(0, $drain);
    }

    /**
     * Calculate energy at a specific minute for a player.
     */
    public static function energyAtMinute(int $physicalAbility, int $age, bool $isGoalkeeper, int $currentMinute, int $minuteEntered = 0, float $tacticalDrainMultiplier = 1.0): float
    {
        $minutesPlayed = max(0, $currentMinute - $minuteEntered);
        $drain = self::drainPerMinute($physicalAbility, $age, $isGoalkeeper, $tacticalDrainMultiplier);

        return max(0, min(100, 100 - $drain * $minutesPlayed));
    }

    /**
     * Calculate average energy over a period (linear drain → midpoint).
     */
    public static function averageEnergy(int $physicalAbility, int $age, bool $isGoalkeeper, int $entryMinute, int $fromMinute, int $toMinute = 93, float $tacticalDrainMultiplier = 1.0): float
    {
        // Player hasn't entered yet — shouldn't happen but return full energy
        if ($entryMinute > $toMinute) {
            return 100.0;
        }

        // If player entered after fromMinute, only average from entry onward
        $effectiveFrom = max($fromMinute, $entryMinute);

        $energyStart = self::energyAtMinute($physicalAbility, $age, $isGoalkeeper, $effectiveFrom, $entryMinute, $tacticalDrainMultiplier);
        $energyEnd = self::energyAtMinute($physicalAbility, $age, $isGoalkeeper, $toMinute, $entryMinute, $tacticalDrainMultiplier);

        return ($energyStart + $energyEnd) / 2;
    }

    /**
     * Convert average energy (0–100) to an effectiveness modifier (min_effectiveness–1.0).
     */
    public static function effectivenessModifier(float $averageEnergy): float
    {
        $minEffectiveness = config('match_simulation.energy.min_effectiveness', 0.5);

        return $minEffectiveness + ($averageEnergy / 100) * (1 - $minEffectiveness);
    }
}
