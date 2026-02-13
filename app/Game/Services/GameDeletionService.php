<?php

namespace App\Game\Services;

use App\Models\Game;
use Illuminate\Support\Facades\DB;

class GameDeletionService
{
    public function delete(Game $game): void
    {
        DB::transaction(function () use ($game) {
            // Delete Spatie event sourcing records (no FK to games, generic aggregate_uuid)
            DB::table('stored_events')->where('aggregate_uuid', $game->id)->delete();
            DB::table('snapshots')->where('aggregate_uuid', $game->id)->delete();

            // Deleting the game cascades to all FK-constrained child tables
            $game->delete();
        });
    }
}
