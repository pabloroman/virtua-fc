<?php

namespace App\Game\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CupDrawConducted extends ShouldBeStored
{
    public function __construct(
        public readonly string $competitionId,
        public readonly int $roundNumber,
        public readonly array $tieIds,
    ) {}
}
