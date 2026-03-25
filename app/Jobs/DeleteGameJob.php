<?php

namespace App\Jobs;

use App\Models\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class DeleteGameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public string $gameId,
    ) {
        $this->onQueue('setup');
    }

    public function handle(): void
    {
        $game = Game::find($this->gameId);

        if (! $game) {
            return;
        }

        Cache::forget("game_owner:{$this->gameId}");

        $game->delete();
    }
}
