<?php

namespace App\Game\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

final class NewSeasonStarted extends ShouldBeStored
{
    public function __construct(
        public string $oldSeason,
        public string $newSeason,
        public array $playerChanges,
    ) {}
}
