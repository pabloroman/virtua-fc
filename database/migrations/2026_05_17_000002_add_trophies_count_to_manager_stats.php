<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->unsignedInteger('trophies_count')
                ->default(0)
                ->after('seasons_completed');
            $table->index(['trophies_count', 'matches_played'], 'ms_trophies_mp');
        });

        // Backfill from manager_trophies.
        DB::statement(<<<'SQL'
            UPDATE manager_stats
            SET trophies_count = sub.cnt
            FROM (
                SELECT game_id, COUNT(*) AS cnt
                FROM manager_trophies
                WHERE game_id IS NOT NULL
                GROUP BY game_id
            ) sub
            WHERE manager_stats.game_id = sub.game_id
        SQL);
    }

    public function down(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->dropIndex('ms_trophies_mp');
            $table->dropColumn('trophies_count');
        });
    }
};
