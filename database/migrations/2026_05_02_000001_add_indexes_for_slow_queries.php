<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Partial index for ContractExpirationProcessor's free-agent purge:
        //   DELETE FROM game_players WHERE game_id = ? AND team_id IS NULL
        // The existing (game_id, team_id) index includes every player; this
        // partial index only contains free agents, so the delete touches a
        // tiny fraction of the b-tree.
        DB::statement(<<<'SQL'
            CREATE INDEX game_players_game_id_team_id_null_index
            ON game_players (game_id)
            WHERE team_id IS NULL
        SQL);

        // Composite index for GamePlayer::hasPendingTransferOffer() EXISTS check.
        // "window" is quoted because it is a reserved keyword in PostgreSQL.
        DB::statement(<<<'SQL'
            CREATE INDEX game_transfers_player_to_team_window_season_index
            ON game_transfers (game_player_id, to_team_id, "window", season)
        SQL);

        // Covers ContractExpirationProcessor's expiring-contract UPDATE,
        // which filters by game_id, team_id IS NOT NULL, and contract_until <= ?.
        DB::statement(<<<'SQL'
            CREATE INDEX game_players_game_id_team_id_contract_until_index
            ON game_players (game_id, team_id, contract_until)
        SQL);

        // Partial index for SeasonArchiveProcessor's top-scorer query, which
        // joins on game_player_id then sorts by goals DESC LIMIT 1. Only rows
        // with goals > 0 are candidates, and the descending order matches the
        // ORDER BY so the planner can serve the LIMIT from an index walk.
        DB::statement(<<<'SQL'
            CREATE INDEX gpms_game_id_goals_index
            ON game_player_match_state (game_id, goals DESC)
            WHERE goals > 0
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS gpms_game_id_goals_index');
        DB::statement('DROP INDEX IF EXISTS game_players_game_id_team_id_contract_until_index');
        DB::statement('DROP INDEX IF EXISTS game_transfers_player_to_team_window_season_index');
        DB::statement('DROP INDEX IF EXISTS game_players_game_id_team_id_null_index');
    }
};
