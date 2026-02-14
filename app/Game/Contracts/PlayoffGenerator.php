<?php

namespace App\Game\Contracts;

use App\Game\DTO\PlayoffRoundConfig;
use App\Models\Game;

interface PlayoffGenerator
{
    /**
     * Which standings positions qualify for playoffs (e.g., [3, 4, 5, 6])
     */
    public function getQualifyingPositions(): array;

    /**
     * Which positions get direct promotion without playoffs (e.g., [1, 2])
     */
    public function getDirectPromotionPositions(): array;

    /**
     * After which matchday should playoffs be triggered
     */
    public function getTriggerMatchday(): int;

    /**
     * Get configuration for a specific round.
     * Reads dates from cup_round_templates in the database.
     */
    public function getRoundConfig(int $round): PlayoffRoundConfig;

    /**
     * Get total number of playoff rounds
     */
    public function getTotalRounds(): int;

    /**
     * Generate matchups for a round based on standings or previous round winners.
     * Returns array of [homeTeamId, awayTeamId] pairs.
     *
     * @return array<array{0: string, 1: string}>
     */
    public function generateMatchups(Game $game, int $round): array;

    /**
     * Check if all playoff rounds are complete
     */
    public function isComplete(Game $game): bool;

    /**
     * Get the competition ID this generator is for
     */
    public function getCompetitionId(): string;
}
