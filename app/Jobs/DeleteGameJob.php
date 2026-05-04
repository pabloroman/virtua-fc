<?php

namespace App\Jobs;

use App\Models\Game;
use App\Modules\Manager\Services\PerformanceHistoryService;
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
        $this->onQueue('cleanup');
    }

    public function handle(): void
    {
        $game = Game::find($this->gameId);

        if (! $game) {
            return;
        }

        Cache::forget("game_owner:{$this->gameId}");
        PerformanceHistoryService::forget($this->gameId);

        // Collect generated player IDs before we delete game_players. The
        // join used to span planes (game_players → players); resolve in two
        // steps instead: pull this game's player_ids from the tenant plane,
        // then filter against the control-plane players table.
        $playerIds = DB::table('game_players')
            ->where('game_id', $this->gameId)
            ->distinct()
            ->pluck('player_id')
            ->all();

        $generatedPlayerIds = [];
        foreach (array_chunk($playerIds, 500) as $chunk) {
            $generatedPlayerIds = array_merge(
                $generatedPlayerIds,
                DB::connection('pgsql_control')
                    ->table('players')
                    ->whereIn('id', $chunk)
                    ->where('transfermarkt_id', 'like', 'gen-%')
                    ->pluck('id')
                    ->all(),
            );
        }

        // Explicit bottom-up deletion: children before parents. Large tables
        // are deleted in batches to reduce lock duration and WAL pressure.

        // Tier 3: deepest children (depend on game_matches / game_players)
        $this->deleteInBatches('match_events');
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
        $this->deleteInBatches('game_matches');
        $this->deleteInBatches('game_players');
        $this->deleteInBatches('game_notifications');
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

        // Clean up generated players that are now orphaned. Splitting the
        // existing whereNotExists into two queries keeps the delete on the
        // control plane and the orphan-detection on the tenant plane.
        if (! empty($generatedPlayerIds)) {
            foreach (array_chunk($generatedPlayerIds, 500) as $chunk) {
                $stillReferenced = DB::table('game_players')
                    ->whereIn('player_id', $chunk)
                    ->distinct()
                    ->pluck('player_id')
                    ->all();

                $orphanIds = array_values(array_diff($chunk, $stillReferenced));
                if ($orphanIds === []) {
                    continue;
                }

                DB::connection('pgsql_control')
                    ->table('players')
                    ->whereIn('id', $orphanIds)
                    ->delete();
            }
        }
    }

    /**
     * Delete rows in batches to reduce lock duration on large tables.
     */
    private function deleteInBatches(string $table, int $batchSize = 500): void
    {
        do {
            $deleted = DB::table($table)
                ->whereIn('id', function ($q) use ($table, $batchSize) {
                    $q->select('id')
                        ->from($table)
                        ->where('game_id', $this->gameId)
                        ->limit($batchSize);
                })
                ->delete();
        } while ($deleted >= $batchSize);
    }
}
