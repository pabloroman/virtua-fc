<?php

namespace App\Modules\Match\Services;

class EnergyCalculator
{
    /**
     * Calculate base energy drain per minute for a player (before proportional scaling).
     *
     * @param  float  $tacticalDrainMultiplier  Combined tactical drain (playing style × pressing)
     */
    public static function drainPerMinute(int $physicalAbility, int $age, bool $isGoalkeeper, float $tacticalDrainMultiplier = 1.0): float
    {
        $baseDrain = config('match_simulation.energy.base_drain_per_minute', 0.55);
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
     *
     * Drain is proportional to starting energy: players who begin a match
     * at lower energy (from congested schedules) drain proportionally less
     * per minute, preventing death spirals.
     *
     * @param  float  $startingEnergy  Energy at match start (equals player fitness, 0-100)
     */
    public static function energyAtMinute(int $physicalAbility, int $age, bool $isGoalkeeper, int $currentMinute, int $minuteEntered = 0, float $tacticalDrainMultiplier = 1.0, float $startingEnergy = 100.0): float
    {
        $minutesPlayed = max(0, $currentMinute - $minuteEntered);
        $baseDrain = self::drainPerMinute($physicalAbility, $age, $isGoalkeeper, $tacticalDrainMultiplier);

        // Proportional drain: scale by starting energy so fatigued players
        // lose less absolute energy per minute, creating stable equilibria
        $effectiveDrain = $baseDrain * ($startingEnergy / 100);

        return max(0, min($startingEnergy, $startingEnergy - $effectiveDrain * $minutesPlayed));
    }

    /**
     * Calculate average energy over a period (linear drain → midpoint).
     *
     * @param  float  $startingEnergy  Energy at match start (equals player fitness, 0-100)
     */
    public static function averageEnergy(int $physicalAbility, int $age, bool $isGoalkeeper, int $entryMinute, int $fromMinute, int $toMinute = 93, float $tacticalDrainMultiplier = 1.0, float $startingEnergy = 100.0): float
    {
        // Player hasn't entered yet — shouldn't happen but return starting energy
        if ($entryMinute > $toMinute) {
            return $startingEnergy;
        }

        // If player entered after fromMinute, only average from entry onward
        $effectiveFrom = max($fromMinute, $entryMinute);

        $energyStart = self::energyAtMinute($physicalAbility, $age, $isGoalkeeper, $effectiveFrom, $entryMinute, $tacticalDrainMultiplier, $startingEnergy);
        $energyEnd = self::energyAtMinute($physicalAbility, $age, $isGoalkeeper, $toMinute, $entryMinute, $tacticalDrainMultiplier, $startingEnergy);

        return ($energyStart + $energyEnd) / 2;
    }

    /**
     * Convert average energy (0–100) to an effectiveness modifier (min_effectiveness–1.0).
     *
     * Linear above 40% energy, with a steeper power-curve drop-off below 40%.
     * This makes exhausted players (high press, late game) noticeably weaker,
     * creating visible tactical consequences for aggressive energy-draining setups.
     */
    public static function effectivenessModifier(float $averageEnergy): float
    {
        $minEffectiveness = config('match_simulation.energy.min_effectiveness', 0.50);
        $normalized = $averageEnergy / 100;

        // Below 40% energy: steeper drop-off via power curve
        if ($normalized < 0.4) {
            // At 40% energy the linear formula gives: min + 0.4 * (1 - min)
            $breakpoint = $minEffectiveness + 0.4 * (1 - $minEffectiveness);
            $dropoff = pow($normalized / 0.4, 1.5);

            return $minEffectiveness + $dropoff * ($breakpoint - $minEffectiveness);
        }

        return $minEffectiveness + $normalized * (1 - $minEffectiveness);
    }
}
