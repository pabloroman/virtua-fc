<?php

namespace App\Game\Contracts;

/**
 * Interface for competition-specific configuration.
 * Each competition (league or cup) can have its own revenue and prize structures.
 */
interface CompetitionConfig
{
    /**
     * Get TV revenue for a given league position (in cents).
     * Returns 0 for cups or competitions without TV revenue.
     */
    public function getTvRevenue(int $position): int;

    /**
     * Get the matchday revenue position factor for a given position.
     * Higher positions typically get more attendance/revenue.
     */
    public function getPositionFactor(int $position): float;

    /**
     * Get the maximum number of positions in this competition.
     */
    public function getMaxPositions(): int;
}
