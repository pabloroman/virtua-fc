<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Covering index for TransferService::getSquadValues():
        //   SELECT team_id, SUM(market_value_cents)
        //   FROM game_players
        //   WHERE game_id = ? AND team_id IN (...)
        //   GROUP BY team_id
        //
        // The existing (game_id, team_id) index already satisfies the WHERE
        // and GROUP BY, but PG still has to fetch market_value_cents from the
        // heap for every matching row. Including the column in the index
        // unlocks an index-only scan, which on a profiled production tick was
        // taking 136 ms (40% of total wall time on a quiet career-actions
        // tick). Called once per tick to size the AI buyer pool.
        DB::statement(<<<'SQL'
            CREATE INDEX game_players_squad_value_aggregate_index
            ON game_players (game_id, team_id)
            INCLUDE (market_value_cents)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS game_players_squad_value_aggregate_index');
    }
};
