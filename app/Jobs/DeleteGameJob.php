<?php

namespace App\Jobs;

use App\Models\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeleteGameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 3;

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

        // Pre-delete the largest tables to avoid a single massive CASCADE transaction
        DB::table('match_events')->where('game_id', $this->gameId)->delete();
        DB::table('game_notifications')->where('game_id', $this->gameId)->delete();
        DB::table('game_matches')->where('game_id', $this->gameId)->delete();

        // CASCADE handles the remaining small tables
        $game->delete();
    }
}
