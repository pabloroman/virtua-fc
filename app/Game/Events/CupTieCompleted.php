<?php

namespace App\Game\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CupTieCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $tieId,
        public readonly string $competitionId,
        public readonly int $roundNumber,
        public readonly string $winnerId,
        public readonly string $loserId,
        public readonly array $resolution,
    ) {}
}
