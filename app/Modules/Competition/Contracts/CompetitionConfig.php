<?php

namespace App\Modules\Competition\Contracts;

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
     * Get the translation key for the top scorer award name.
     * E.g., 'season.pichichi' for La Liga, 'season.top_scorer' for generic leagues.
     */
    public function getTopScorerAwardName(): string;

    /**
     * Get the translation key for the best goalkeeper award name.
     * E.g., 'season.zamora' for La Liga, 'season.best_goalkeeper' for generic leagues.
     */
    public function getBestGoalkeeperAwardName(): string;

    /**
     * Get the standings zones for this competition (UCL, UEL, relegation, promotion, etc.).
     *
     * Each zone has:
     * - minPosition: First position in the zone (inclusive)
     * - maxPosition: Last position in the zone (inclusive)
     * - borderColor: Tailwind border color class (e.g., 'blue-500')
     * - bgColor: Tailwind background color class for legend (e.g., 'bg-blue-500')
     * - label: Translation key for the zone name
     *
     * @return array<array{minPosition: int, maxPosition: int, borderColor: string, bgColor: string, label: string}>
     */
    public function getStandingsZones(): array;

    /**
     * Get prize money for advancing past a knockout round (in cents).
     * Returns 0 for competitions without knockout prize money.
     */
    public function getKnockoutPrizeMoney(int $roundNumber): int;
}
