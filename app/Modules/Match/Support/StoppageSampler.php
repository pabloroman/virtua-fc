<?php

namespace App\Modules\Match\Support;

/**
 * Samples realistic stoppage durations per half.
 *
 * Driven by config('match_simulation.stoppage') so distributions can be
 * tuned without changing code. Uses a clamped Poisson around the configured
 * mean — picks a reasonable spread (most matches near the mean, with a tail
 * of long-stoppage games), then clamps to the configured min/max so unlucky
 * draws can't produce 20-minute stoppage.
 */
final class StoppageSampler
{
    public function sampleFirstHalf(): int
    {
        return $this->sample('first_half');
    }

    public function sampleSecondHalf(): int
    {
        return $this->sample('second_half');
    }

    public function sampleEtFirstHalf(): int
    {
        return $this->sample('et_first_half');
    }

    public function sampleEtSecondHalf(): int
    {
        return $this->sample('et_second_half');
    }

    private function sample(string $period): int
    {
        $cfg = config("match_simulation.stoppage.{$period}");
        $mean = (float) ($cfg['mean'] ?? 3.0);
        $min = (int) ($cfg['min'] ?? 0);
        $max = (int) ($cfg['max'] ?? 10);

        $draw = $this->poisson($mean);

        return max($min, min($max, $draw));
    }

    /**
     * Knuth's algorithm — fine for the small lambdas we use here.
     */
    private function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        $l = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $l);

        return $k - 1;
    }
}
