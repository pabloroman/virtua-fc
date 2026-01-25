<?php

namespace App\Game\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

final class MatchdayAdvanced extends ShouldBeStored
{
    public function __construct(
        public int $matchday,
        public string $currentDate,
    ) {}
}
