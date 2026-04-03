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

        // Collect generated player IDs before we delete game_players
        $generatedPlayerIds = DB::table('game_players')
            ->join('players', 'players.id', '=', 'game_players.player_id')
            ->where('game_players.game_id', $this->gameId)
            ->where('players.transfermarkt_id', 'like', 'gen-%')
            ->pluck('game_players.player_id')
            ->all();

        // Explicit bottom-up deletion: children before parents, each in its own
        // transaction. This avoids a single massive CASCADE transaction, reducing
        // lock contention and WAL pressure.

        // Tier 3: deepest children (depend on game_matches / game_players)
        DB::table('match_events')->where('game_id', $this->gameId)->delete();
        DB::table('player_suspensions')
            ->whereIn('game_player_id', function ($q) {
                $q->select('id')->from('game_players')->where('game_id', $this->gameId);
            })
            ->delete();

        // Tier 2: tables that reference game_players (must go before game_players)
        DB::table('transfer_offers')->where('game_id', $this->gameId)->delete();
        DB::table('renewal_negotiations')->where('game_id', $this->gameId)->delete();
        DB::table('shortlisted_players')->where('game_id', $this->gameId)->delete();
        DB::table('game_transfers')->where('game_id', $this->gameId)->delete();
        DB::table('loans')->where('game_id', $this->gameId)->delete();
        DB::table('financial_transactions')->where('game_id', $this->gameId)->delete();

        // Tier 1: direct children of games
        DB::table('game_matches')->where('game_id', $this->gameId)->delete();
        DB::table('game_players')->where('game_id', $this->gameId)->delete();
        DB::table('game_notifications')->where('game_id', $this->gameId)->delete();
        DB::table('game_standings')->where('game_id', $this->gameId)->delete();
        DB::table('game_finances')->where('game_id', $this->gameId)->delete();
        DB::table('game_investments')->where('game_id', $this->gameId)->delete();
        DB::table('game_tactics')->where('game_id', $this->gameId)->delete();
        DB::table('game_tactical_presets')->where('game_id', $this->gameId)->delete();
        DB::table('cup_ties')->where('game_id', $this->gameId)->delete();
        DB::table('scout_reports')->where('game_id', $this->gameId)->delete();
        DB::table('competition_entries')->where('game_id', $this->gameId)->delete();
        DB::table('team_reputations')->where('game_id', $this->gameId)->delete();
        DB::table('academy_players')->where('game_id', $this->gameId)->delete();
        DB::table('season_archives')->where('game_id', $this->gameId)->delete();
        DB::table('simulated_seasons')->where('game_id', $this->gameId)->delete();
        DB::table('budget_loans')->where('game_id', $this->gameId)->delete();

        // Root: game row itself (nothing left to cascade)
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
