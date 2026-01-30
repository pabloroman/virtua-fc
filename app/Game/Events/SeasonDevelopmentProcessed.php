<?php

namespace App\Game\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Event recorded when season-end development is processed for a team.
 *
 * Contains all player ability changes that occurred during development.
 */
final class SeasonDevelopmentProcessed extends ShouldBeStored
{
    /**
     * @param string $season The season identifier (e.g., "2024")
     * @param string $teamId The team whose players were developed
     * @param array $playerChanges Array of player changes:
     *        [{playerId, techBefore, techAfter, physBefore, physAfter}, ...]
     */
    public function __construct(
        public string $season,
        public string $teamId,
        public array $playerChanges,
    ) {}
}
