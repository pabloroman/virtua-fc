<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Guard against duplicate squad numbers in game_player_templates.
 *
 * The downstream game_players table enforces `(game_id, team_id, number)`
 * uniqueness, so template dupes cause SetupNewGame's insertOrIgnore to
 * silently drop rows and then FK-fail on game_player_match_state. The
 * partial index makes such anomalies fail loudly at ingest time instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX game_player_templates_season_team_number_unique
            ON game_player_templates (season, team_id, number)
            WHERE number IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS game_player_templates_season_team_number_unique');
    }
};
