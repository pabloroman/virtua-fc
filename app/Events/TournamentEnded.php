<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Foundation\Events\Dispatchable;

// Fires when the last match of a tournament-mode game is finalized, BEFORE
// the snapshot is created or the game is soft-deleted. Listeners drive the
// three side effects (activation record, snapshot, soft-delete) in order.
// Distinct from TournamentCompleted, which fires AFTER the snapshot exists
// and carries the champion flag for downstream consumers.
class TournamentEnded
{
    use Dispatchable;

    public function __construct(
        public readonly Game $game,
    ) {}
}
