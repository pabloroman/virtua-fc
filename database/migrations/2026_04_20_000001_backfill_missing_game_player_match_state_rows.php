<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill game_player_match_state rows for every existing game_player
     * that doesn't already have one.
     *
     * Previously the satellite table was sparse — only "active scope" players
     * (teams in the user's domestic competitions) carried a row, and foreign
     * transfer-pool players relied on the defaults baked into
     * {@see \App\Models\GamePlayer}'s accessor delegates. The lazy
     * ensureExistForGamePlayers path filled the gap at matchday time whenever
     * an out-of-scope team entered play (e.g. European qualification).
     *
     * Going forward every game_player is guaranteed a satellite row at
     * creation time. This migration brings existing games up to the new
     * invariant so the matchday-time ensure path can be retired.
     *
     * Idempotent: ON CONFLICT (game_player_id) DO NOTHING skips rows that
     * already exist, so re-running on an up-to-date database is a no-op.
     *
     * Chunked via keyset pagination on gp.id so each batch commits
     * independently. A single INSERT ... SELECT over the full table generated
     * too much WAL and held locks long enough to time out deploys.
     */

    /**
     * Disable the migration transaction so each chunk commits on its own.
     * Otherwise every chunk would accumulate in a single transaction,
     * defeating the point of batching (WAL, lock duration, memory).
     */
    public $withinTransaction = false;

    private const BATCH_SIZE = 10_000;

    public function up(): void
    {
        // UUIDs sort lexicographically; start below the minimum possible value.
        $lastId = '00000000-0000-0000-0000-000000000000';

        while (true) {
            $nextId = DB::scalar(<<<'SQL'
                WITH batch AS MATERIALIZED (
                    SELECT gp.id, gp.game_id
                    FROM game_players gp
                    WHERE gp.id > ?
                    ORDER BY gp.id
                    LIMIT ?
                ), inserted AS (
                    INSERT INTO game_player_match_state (
                        game_player_id, game_id, fitness, morale, injury_until, injury_type,
                        appearances, season_appearances, goals, own_goals, assists,
                        yellow_cards, red_cards, goals_conceded, clean_sheets
                    )
                    SELECT id, game_id, 80, 80, NULL, NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0
                    FROM batch
                    ON CONFLICT (game_player_id) DO NOTHING
                    RETURNING 1
                )
                SELECT id FROM batch ORDER BY id DESC LIMIT 1
            SQL, [$lastId, self::BATCH_SIZE]);

            if ($nextId === null) {
                break;
            }

            $lastId = $nextId;
        }
    }

    public function down(): void
    {
        // No-op. We can't selectively drop only the backfilled rows without
        // tracking which ones were added here, and rolling back the whole
        // table would blow away every game's match state.
    }
};
