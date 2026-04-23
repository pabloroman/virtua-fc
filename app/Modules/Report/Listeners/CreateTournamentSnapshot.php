<?php

namespace App\Modules\Report\Listeners;

use App\Events\TournamentEnded;
use App\Models\TournamentSummary;
use App\Modules\Report\Services\TournamentSnapshotService;

class CreateTournamentSnapshot
{
    public function __construct(
        private readonly TournamentSnapshotService $snapshotService,
    ) {}

    public function handle(TournamentEnded $event): void
    {
        $game = $event->game;

        // Defensive re-check: the unique index on tournament_summaries.original_game_id
        // is the authoritative idempotency guard, but a short-circuit here avoids
        // doing the (expensive) summary build work twice if the detector races
        // with another path.
        if (TournamentSummary::where('original_game_id', $game->id)->exists()) {
            return;
        }

        $this->snapshotService->createSnapshot($game);
    }
}
