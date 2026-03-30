<?php

namespace App\Modules\Transfer\Services;

/**
 * Shared evaluation logic for wage negotiations.
 *
 * Used by ContractService for renewals, transfer terms,
 * pre-contract terms, and free agent negotiations.
 */
class WageNegotiationEvaluator
{
    /** Counter threshold: offer must be >= 85% of minimum acceptable to get a counter */
    private const COUNTER_THRESHOLD = 0.85;

    /** Default disposition-to-flexibility scaling factor */
    private const DEFAULT_FLEXIBILITY_RATIO = 0.30;

    /**
     * Evaluate a wage offer against a player's demand.
     *
     * @param  int  $offerWage  The wage being offered (cents)
     * @param  int  $offeredYears  Contract years being offered
     * @param  int  $playerDemand  The player's wage demand (cents)
     * @param  int  $preferredYears  Player's preferred contract length
     * @param  float  $disposition  Player willingness (0.10–0.95)
     * @param  int  $round  Current negotiation round
     * @param  int  $maxRounds  Maximum allowed rounds
     * @param  int|null  $salaryFloor  Minimum acceptable wage (e.g. current wage for renewals)
     * @param  int|null  $previousCounter  Previous counter-offer cap (never raise above this)
     * @param  float|null  $flexibilityRatio  Override for flexibility scaling (default 0.30, renewals use 0.18)
     * @return array{result: string, counterWage: int|null}
     */
    public function evaluate(
        int $offerWage,
        int $offeredYears,
        int $playerDemand,
        int $preferredYears,
        float $disposition,
        int $round,
        int $maxRounds,
        ?int $salaryFloor = null,
        ?int $previousCounter = null,
        ?float $flexibilityRatio = null,
    ): array {
        $flexibility = $disposition * ($flexibilityRatio ?? self::DEFAULT_FLEXIBILITY_RATIO);
        $minimumAcceptable = (int) ($playerDemand * (1.0 - $flexibility));

        // Apply salary floor (renewal: players don't take pay cuts)
        if ($salaryFloor !== null) {
            $minimumAcceptable = max($minimumAcceptable, $salaryFloor);
        }

        $yearsModifier = $this->calculateYearsModifier($offeredYears, $preferredYears);
        $effectiveOffer = (int) ($offerWage * $yearsModifier);

        if ($effectiveOffer >= $minimumAcceptable) {
            return ['result' => 'accepted', 'counterWage' => null];
        }

        // Check if close enough for a counter
        $counterThreshold = (int) ($minimumAcceptable * self::COUNTER_THRESHOLD);

        if ($effectiveOffer >= $counterThreshold && $round < $maxRounds) {
            $counterWage = (int) (($minimumAcceptable + $playerDemand) / 2);
            $counterWage = $this->roundCounterOffer($counterWage, $minimumAcceptable);

            // Never raise above previous counter
            if ($previousCounter !== null && $counterWage > $previousCounter) {
                $counterWage = $previousCounter;
            }

            return ['result' => 'countered', 'counterWage' => $counterWage];
        }

        return ['result' => 'rejected', 'counterWage' => null];
    }

    /**
     * Calculate the years modifier based on offered vs preferred years.
     */
    private function calculateYearsModifier(int $offeredYears, int $preferredYears): float
    {
        $diff = $offeredYears - $preferredYears;

        return match ($diff) {
            0 => 1.00,
            1 => 1.08,
            2 => 1.15,
            -1 => 0.90,
            -2 => 0.80,
            default => $diff > 0 ? 1.15 : 0.80,
        };
    }

    /**
     * Adaptive wage rounding: €10K for wages under €1M, €100K otherwise.
     * Ensures the counter is never below minimumAcceptable.
     */
    private function roundCounterOffer(int $wage, int $minimumAcceptable): int
    {
        $unit = $wage >= 1_000_000_00 ? 100_000_00 : 10_000_00;
        $rounded = (int) (round($wage / $unit) * $unit);

        return max($rounded, $minimumAcceptable);
    }
}
