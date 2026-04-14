<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phase 1: Add nullable column
        Schema::table('player_suspensions', function (Blueprint $table) {
            $table->uuid('game_id')->nullable()->after('game_player_id');
        });

        // Phase 2: Backfill from game_players
        DB::statement(<<<'SQL'
            UPDATE player_suspensions
            SET game_id = gp.game_id
            FROM game_players gp
            WHERE player_suspensions.game_player_id = gp.id
        SQL);

        // Phase 3: Add NOT NULL constraint + FK
        Schema::table('player_suspensions', function (Blueprint $table) {
            $table->uuid('game_id')->nullable(false)->change();
            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
        });

        // Phase 4: Partial index for the hot-path query (active suspensions per game+competition).
        // Excludes rows where matches_remaining = 0 (kept around for yellow_card tracking),
        // keeping the index tight and making suspendedPlayerIdsForCompetition() an index-only scan.
        DB::statement(<<<'SQL'
            CREATE INDEX player_suspensions_active_idx
            ON player_suspensions (game_id, competition_id)
            WHERE matches_remaining > 0
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS player_suspensions_active_idx');

        Schema::table('player_suspensions', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->dropColumn('game_id');
        });
    }
};
