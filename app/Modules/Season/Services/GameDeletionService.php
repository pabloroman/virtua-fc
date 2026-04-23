<?php

namespace App\Modules\Season\Services;

use App\Jobs\DeleteGameJob;
use App\Models\Game;
use App\Modules\Manager\Services\PerformanceHistoryService;
use Illuminate\Support\Facades\Cache;

class GameDeletionService
{
    public function delete(Game $game): void
    {
        if ($game->isDeleting()) {
            return;
        }

        Cache::forget("game_owner:{$game->id}");
        PerformanceHistoryService::forget($game->id);

        $game->update(['deleting_at' => now()]);

        DeleteGameJob::dispatch($game->id);
    }
}
