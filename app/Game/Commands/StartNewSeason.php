<?php

namespace App\Game\Commands;

/**
 * Command to start a new season.
 */
final class StartNewSeason
{
    public function __construct(
        public readonly string $oldSeason,
        public readonly string $newSeason,
        public readonly array $playerChanges,
    ) {}
}
