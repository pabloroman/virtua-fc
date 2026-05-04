<?php

namespace App\Modules\Player\Jobs;

use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 of the player-data planes refactor: copy biographical fields
 * (transfermarkt_id, name, date_of_birth, nationality, height, foot) from
 * the control-plane `players` table into one game's `game_players` rows.
 *
 * Per-game scope: rows for a single game are heap-clustered (inserted
 * together during game creation), so each UPDATE touches a small,
 * contiguous slice of the table — a handful of page-fetch round trips
 * per game on Neon's network-storage model instead of the random scatter
 * of a UUID-PK-batched bulk update.
 *
 * Plane-safe: reads Players from `pgsql_control` and writes to the default
 * tenant connection in separate queries — no cross-plane JOIN. The bulk
 * UPDATE uses a `VALUES` join rather than a subquery against players.
 *
 * Idempotent: every UPDATE filters by `name IS NULL`, so re-dispatch is a
 * no-op once a game is fully backfilled.
 */
class BackfillGamePlayerBiography implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public string $gameId) {}

    public function handle(): int
    {
        $playerIds = DB::table('game_players')
            ->where('game_id', $this->gameId)
            ->whereNull('name')
            ->whereNotNull('player_id')
            ->distinct()
            ->pluck('player_id');

        if ($playerIds->isEmpty()) {
            return 0;
        }

        $players = Player::query()
            ->whereIn('id', $playerIds)
            ->get(['id', 'transfermarkt_id', 'name', 'date_of_birth', 'nationality', 'height', 'foot']);

        if ($players->isEmpty()) {
            return 0;
        }

        // Chunk to stay under PostgreSQL's 65,535-parameter wire-protocol cap
        // on prepared statements. Each player consumes 7 placeholders + 1 for
        // game_id, so 1000 per UPDATE leaves plenty of headroom and still
        // keeps round-trip count low for the 9k-10k-player games.
        $totalUpdated = 0;
        foreach ($players->chunk(1000) as $chunk) {
            $placeholders = [];
            $bindings = [];
            foreach ($chunk as $player) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?)';
                array_push(
                    $bindings,
                    $player->id,
                    $player->transfermarkt_id,
                    $player->name,
                    $player->date_of_birth?->toDateString(),
                    $player->nationality !== null ? json_encode($player->nationality) : null,
                    $player->height,
                    $player->foot,
                );
            }
            $bindings[] = $this->gameId;
            $values = implode(',', $placeholders);

            $totalUpdated += DB::update(
                "UPDATE game_players gp
                 SET transfermarkt_id = src.transfermarkt_id,
                     name = src.name,
                     date_of_birth = src.date_of_birth::date,
                     nationality = src.nationality::jsonb,
                     height = src.height,
                     foot = src.foot
                 FROM (VALUES {$values}) AS src(player_id, transfermarkt_id, name, date_of_birth, nationality, height, foot)
                 WHERE gp.game_id = ?
                   AND gp.player_id = src.player_id::uuid
                   AND gp.name IS NULL",
                $bindings
            );
        }

        return $totalUpdated;
    }
}
