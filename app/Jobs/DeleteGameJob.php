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

        // Collect generated player IDs before cascade removes game_players
        $generatedPlayerIds = DB::table('game_players')
            ->join('players', 'players.id', '=', 'game_players.player_id')
            ->where('game_players.game_id', $this->gameId)
            ->where('players.transfermarkt_id', 'like', 'gen-%')
            ->pluck('game_players.player_id')
            ->all();

        // Pre-delete the largest tables to avoid a single massive CASCADE transaction
        DB::table('match_events')->where('game_id', $this->gameId)->delete();
        DB::table('game_notifications')->where('game_id', $this->gameId)->delete();
        DB::table('game_matches')->where('game_id', $this->gameId)->delete();

        // CASCADE handles the remaining small tables (including game_players)
        $game->delete();

        // Clean up generated players that are now orphaned
        if (! empty($generatedPlayerIds)) {
            foreach (array_chunk($generatedPlayerIds, 500) as $chunk) {
                DB::table('players')
                    ->whereIn('id', $chunk)
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('game_players')
                            ->whereColumn('game_players.player_id', 'players.id');
                    })
                    ->delete();
            }
        }
    }
}
