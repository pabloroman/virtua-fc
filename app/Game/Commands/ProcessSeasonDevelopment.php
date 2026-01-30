<?php

namespace App\Game\Commands;

/**
 * Command to process season-end development for a team.
 */
final readonly class ProcessSeasonDevelopment
{
    public function __construct(
        public string $season,
        public string $teamId,
        public array $playerChanges,
    ) {}
}
