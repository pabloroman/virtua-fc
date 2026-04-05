<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->boolean('standings_applied')->default(false);
        });

        // Backfill: mark all played league matches as already applied
        DB::statement("
            UPDATE game_matches SET standings_applied = true
            WHERE played = true
            AND cup_tie_id IS NULL
            AND competition_id IN (
                SELECT id FROM competitions WHERE handler_type IN ('league', 'league_with_playoff', 'swiss_format', 'group_stage_cup')
            )
        ");
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('standings_applied');
        });
    }
};
