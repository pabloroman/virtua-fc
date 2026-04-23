<?php

namespace App\Modules\Season\Listeners;

use App\Events\TournamentEnded;
use App\Models\TournamentSummary;
use App\Modules\Match\Events\MatchFinalized;

class DetectTournamentEnded
{
    public function handle(MatchFinalized $event): void
    {
        $game = $event->game;

        if (! $game->isTournamentMode()) {
            return;
        }

        if ($game->deleting_at !== null) {
            return;
        }

        $hasUnplayed = $game->matches()->where('played', false)->exists();

        if ($hasUnplayed) {
            return;
        }

        $summaryExists = TournamentSummary::where('original_game_id', $game->id)->exists();

        if ($summaryExists) {
            return;
        }

        TournamentEnded::dispatch($game);
    }
}
