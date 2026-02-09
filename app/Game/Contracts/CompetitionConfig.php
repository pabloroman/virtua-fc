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
     * Get the season goal for a team based on reputation level.
     *
     * @param string $reputation One of ClubProfile::REPUTATION_* constants
     * @return string One of Game::GOAL_* constants
     */
    public function getSeasonGoal(string $reputation): string;

    /**
     * Get the target position for achieving a season goal.
     *
     * @param string $goal One of Game::GOAL_* constants
     * @return int The target position (finish at or above)
     */
    public function getGoalTargetPosition(string $goal): int;

    /**
     * Get the available season goals for this competition.
     *
     * @return array<string, array{targetPosition: int, label: string}>
     */
    public function getAvailableGoals(): array;

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
     * Get commercial revenue per seat for a given reputation level (in cents).
     */
    public function getCommercialPerSeat(string $reputation): int;

    /**
     * Get matchday revenue per seat for a given reputation level (in cents).
     */
    public function getRevenuePerSeat(string $reputation): int;
}
