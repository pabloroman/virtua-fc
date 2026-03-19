<?php

namespace App\Modules\Season\Services;

use App\Jobs\DeleteGameJob;
use App\Models\Game;
use Illuminate\Support\Facades\Cache;

class GameDeletionService
{
    public function delete(Game $game): void
    {
        if ($game->isDeleting()) {
            return;
        }

        Cache::forget("game_owner:{$game->id}");

        $game->update(['deleting_at' => now()]);

        DeleteGameJob::dispatch($game->id);
    }
}
