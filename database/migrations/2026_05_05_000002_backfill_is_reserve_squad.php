<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 2 of the is_reserve_squad denormalization. Backfills the new
     * boolean column on game_players (tenant) and game_player_templates
     * (control) from teams.parent_team_id (control), without using a
     * cross-plane subquery: read the reserve-team-id set into PHP first, then
     * run plain UPDATEs on each plane.
     *
     * Reserve teams are ~5–10 rows worldwide, so a single UPDATE per plane is
     * cheap; no per-game chunking is needed (unlike the biography backfill,
     * which copied six wide columns per row).
     */
    public function up(): void
    {
        $reserveTeamIds = DB::connection('pgsql_control')
            ->table('teams')
            ->whereNotNull('parent_team_id')
            ->pluck('id')
            ->all();

        if (!empty($reserveTeamIds)) {
            DB::table('game_players')
                ->whereIn('team_id', $reserveTeamIds)
                ->whereNull('is_reserve_squad')
                ->update(['is_reserve_squad' => true]);

            DB::connection('pgsql_control')
                ->table('game_player_templates')
                ->whereIn('team_id', $reserveTeamIds)
                ->whereNull('is_reserve_squad')
                ->update(['is_reserve_squad' => true]);
        }

        DB::table('game_players')
            ->whereNull('is_reserve_squad')
            ->update(['is_reserve_squad' => false]);

        DB::connection('pgsql_control')
            ->table('game_player_templates')
            ->whereNull('is_reserve_squad')
            ->update(['is_reserve_squad' => false]);
    }

    public function down(): void
    {
        DB::table('game_players')->update(['is_reserve_squad' => null]);

        DB::connection('pgsql_control')
            ->table('game_player_templates')
            ->update(['is_reserve_squad' => null]);
    }
};
