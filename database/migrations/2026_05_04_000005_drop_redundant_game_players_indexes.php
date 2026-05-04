<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // Redundant with game_players_game_id_team_id_position_index and
        // game_players_game_id_team_id_contract_until_index, both of which
        // share (game_id, team_id) as their leading prefix and can serve
        // any (game_id, team_id) lookup.
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS game_players_game_id_team_id_index');

        // Dropping the contract-expiration covering index. The
        // ContractExpirationProcessor query falls back to scanning
        // (game_id, team_id, position) and filtering by contract_until.
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS game_players_game_id_team_id_contract_until_index');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY IF NOT EXISTS game_players_game_id_team_id_index
            ON game_players (game_id, team_id)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY IF NOT EXISTS game_players_game_id_team_id_contract_until_index
            ON game_players (game_id, team_id, contract_until)
        SQL);
    }
};
