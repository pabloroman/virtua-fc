<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

// Fires AFTER a TournamentSummary has been persisted, carrying just the
// user id and champion flag. Distinct from TournamentEnded, which fires
// BEFORE the snapshot and drives the snapshot/delete listener chain.
class TournamentCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly bool $isChampion,
    ) {}
}
