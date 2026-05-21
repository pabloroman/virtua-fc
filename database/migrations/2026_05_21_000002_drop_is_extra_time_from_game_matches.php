<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the redundant `is_extra_time` boolean.
     *
     * Replaced by `GameMatch::reachedExtraTime()`, which derives the answer
     * from `home_score_et !== null` (the canonical source). Keeping a boolean
     * in sync with the ET-score columns was always a footgun — the cup-tie
     * resolver and listeners had to remember to set both.
     *
     * All read sites switched to `reachedExtraTime()` in the same release.
     * Snapshot JSON keys (`tournament_summary`, `manager_stats_rebuilder`,
     * `season_archive`) keep the `is_extra_time` field name for backwards
     * compatibility with historical data — they now populate it from
     * `reachedExtraTime()` instead of the column.
     */
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('is_extra_time');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->boolean('is_extra_time')->default(false)->after('cup_tie_id');
        });

        // Backfill on rollback so the boolean matches the canonical ET score.
        \Illuminate\Support\Facades\DB::statement(<<<SQL
            UPDATE game_matches SET is_extra_time = true
            WHERE home_score_et IS NOT NULL
        SQL);
    }
};
