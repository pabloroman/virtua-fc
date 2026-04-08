<?php

namespace App\Modules\Squad\Services;

class PlayerAttributeSampler
{
    /**
     * Generate a normally distributed random value using the Box-Muller transform.
     */
    public function gaussianRandom(float $mean, float $stdDev): float
    {
        $u1 = mt_rand(1, PHP_INT_MAX) / PHP_INT_MAX;
        $u2 = mt_rand(1, PHP_INT_MAX) / PHP_INT_MAX;

        $z = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);

        return $mean + $stdDev * $z;
    }

    /**
     * Sample an ability value from a gaussian distribution, clamped to the given range.
     */
    public function sampleAbility(float $mean, float $stdDev, int $min, int $max): int
    {
        return max($min, min($max, (int) round($this->gaussianRandom($mean, $stdDev))));
    }

    /**
     * Generate potential and its visible range from the player's current best ability.
     *
     * Algorithm:
     * - potential = currentBest + gaussian upside (floored at 0)
     * - Apply minimum floor guarantee
     * - Cap at maxPotential, ensure >= currentBest
     * - Visible range: random variance band of 3-8 points around true potential
     *
     * @return array{potential: int, potentialLow: int, potentialHigh: int}
     */
    public function generatePotentialFromAbility(
        int $currentBest,
        int $upsideMean,
        int $upsideStdDev,
        int $floor,
        int $maxPotential = 88,
    ): array {
        $upside = max(0, (int) round($this->gaussianRandom($upsideMean, $upsideStdDev)));
        $potential = $currentBest + $upside;

        $potential = max($potential, $floor);
        $potential = min($maxPotential, max($potential, $currentBest));

        $variance = rand(3, 8);
        $potentialLow = max($potential - $variance, $currentBest);
        $potentialHigh = min($potential + $variance, 99);

        return [
            'potential' => $potential,
            'potentialLow' => $potentialLow,
            'potentialHigh' => $potentialHigh,
        ];
    }
}
