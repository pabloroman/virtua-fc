<?php

namespace App\Modules\Finance\DTOs;

/**
 * A snapshot of the user's wage-cap state for a single season.
 *
 * All monetary values are in cents.
 *
 *   cap = round(projectedRevenue * ratio) + bufferCents
 *   headroom = cap - committedWages
 *
 * Headroom can be negative (already over the cap).
 */
final readonly class WageHeadroom
{
    public function __construct(
        public int $season,
        public int $projectedRevenue,
        public int $currentSquadWages,
        public int $pendingPreContractWages,
        public float $ratio,
        public int $bufferCents,
    ) {}

    public function committedWages(): int
    {
        return $this->currentSquadWages + $this->pendingPreContractWages;
    }

    /**
     * The hard cap on annual wages (including buffer tolerance).
     */
    public function cap(): int
    {
        return (int) round($this->projectedRevenue * $this->ratio) + $this->bufferCents;
    }

    /**
     * How much annual wage the user can still commit without breaching the cap.
     * Negative when already over.
     */
    public function headroom(): int
    {
        return $this->cap() - $this->committedWages();
    }

    /**
     * The shortfall (cents) when committing an extra $additionalWage would
     * exceed the cap. Zero when the new total still fits.
     */
    public function shortfallFor(int $additionalWage): int
    {
        $overflow = ($this->committedWages() + $additionalWage) - $this->cap();

        return max(0, $overflow);
    }

    /**
     * Wage-to-revenue ratio (0..1+) for display. Returns 0 when revenue is 0.
     */
    public function utilisation(): float
    {
        if ($this->projectedRevenue <= 0) {
            return 0.0;
        }

        return $this->committedWages() / $this->projectedRevenue;
    }

    public function toArray(): array
    {
        return [
            'season' => $this->season,
            'projected_revenue' => $this->projectedRevenue,
            'current_squad_wages' => $this->currentSquadWages,
            'pending_pre_contract_wages' => $this->pendingPreContractWages,
            'committed_wages' => $this->committedWages(),
            'ratio' => $this->ratio,
            'buffer_cents' => $this->bufferCents,
            'cap' => $this->cap(),
            'headroom' => $this->headroom(),
            'utilisation' => $this->utilisation(),
        ];
    }
}
