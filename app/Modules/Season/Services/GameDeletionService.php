<?php

namespace App\Modules\Season\Services;

use App\Jobs\DeleteGameJob;
use App\Models\Game;

class GameDeletionService
{
    public function delete(Game $game): void
    {
        $game->update(['deleting_at' => now()]);

        DeleteGameJob::dispatch($game->id);
    }
}
