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
}
