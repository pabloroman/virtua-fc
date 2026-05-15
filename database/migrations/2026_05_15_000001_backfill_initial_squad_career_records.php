<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill UserSquadCareerRecord rows for the initial squad of every
     * existing game. Before this fix, SetupNewGame only inserted game_players
     * for the initial squad and never created career records, so the
     * season-close snapshot processor had nothing to append per-season stats
     * to for those players. Only transfer-acquired players had records.
     *
     * Strategy: for every game_player whose team is owned by the user (the
     * game's team_id or reserve_team_id) and that does not already have a
     * career record, insert one. joined_from is left NULL — we have no
     * reliable origin signal for legacy starters, and the badge/label
     * components render nothing for the NULL case. joined_season is set to
     * the game's current season; for games already past their first season
     * this is cosmetically inaccurate on the "Joined" label but is otherwise
     * harmless: it does not affect the trajectory snapshotting going forward.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO user_squad_career_records (
                id, game_player_id, game_id, team_id, joined_season, joined_from, season_stats
            )
            SELECT
                gen_random_uuid(),
                gp.id,
                gp.game_id,
                gp.team_id,
                CAST(g.season AS INTEGER),
                NULL,
                '{}'::jsonb
            FROM game_players gp
            JOIN games g ON g.id = gp.game_id
            WHERE gp.team_id IS NOT NULL
              AND (gp.team_id = g.team_id OR gp.team_id = g.reserve_team_id)
              AND NOT EXISTS (
                  SELECT 1
                  FROM user_squad_career_records r
                  WHERE r.game_player_id = gp.id
              )
        SQL);
    }

    public function down(): void
    {
        // Non-reversible: we cannot distinguish backfilled rows from records
        // organically created at season setup once both exist.
    }
};
