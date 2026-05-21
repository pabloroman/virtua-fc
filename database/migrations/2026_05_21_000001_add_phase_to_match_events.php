<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase-aware match events.
     *
     * Adds:
     *   - match_events.phase           (string enum)
     *   - match_events.stoppage_minute (nullable tinyint)
     *   - game_matches.first_half_stoppage / second_half_stoppage / et_*_stoppage
     *
     * Backfills both tables with a deterministic heuristic so historical
     * matches render correctly and so phase-based reads work immediately.
     *
     * After backfill, `phase` is made NOT NULL. The existing `minute` column
     * is rewritten to be the base minute *within the phase* (so an old row
     * with minute=92 in a non-ET match becomes minute=90, stoppage_minute=2).
     *
     * See app/Modules/Match/Support/MinuteCoordinates.php for the canonical
     * mapping between raw absolute minutes and (phase, base, stoppage).
     */
    public function up(): void
    {
        // 1. game_matches stoppage columns.
        Schema::table('game_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('first_half_stoppage')->default(0)->after('away_score');
            $table->unsignedTinyInteger('second_half_stoppage')->default(0)->after('first_half_stoppage');
            $table->unsignedTinyInteger('et_first_half_stoppage')->nullable()->after('away_score_et');
            $table->unsignedTinyInteger('et_second_half_stoppage')->nullable()->after('et_first_half_stoppage');
        });

        // Historical matches: assume "typical" stoppage so the display reads
        // like a real match. Played matches with regulation goals at minute
        // 91-93 stay consistent because we'll bucket those into stoppage.
        DB::table('game_matches')
            ->where('played', true)
            ->update([
                'first_half_stoppage' => 0,
                'second_half_stoppage' => 3,
            ]);
        DB::table('game_matches')
            ->where('played', true)
            ->where('is_extra_time', true)
            ->update([
                'et_first_half_stoppage' => 0,
                'et_second_half_stoppage' => 0,
            ]);

        // 2. match_events columns.
        Schema::table('match_events', function (Blueprint $table) {
            $table->string('phase', 24)->nullable()->after('minute');
            $table->unsignedTinyInteger('stoppage_minute')->nullable()->after('phase');
        });

        // 3. Backfill phase + decompose `minute` into (base, stoppage_minute).
        //
        // Rules — see proposal:
        //   minute ≤ 45                            → FIRST_HALF
        //   46..90                                 → SECOND_HALF
        //   91..93, match NOT in extra time        → SECOND_HALF_STOPPAGE (minute=90, stoppage=minute-90)
        //   91..93, match in extra time            → ET_FIRST_HALF (current generator put them there)
        //   94..105                                → ET_FIRST_HALF
        //   106..120                               → ET_SECOND_HALF
        //   > 120                                  → ET_SECOND_HALF_STOPPAGE
        $driver = DB::connection()->getDriverName();

        // PostgreSQL bulk UPDATE in one shot per bucket — small N and we're
        // running once at migration time, so clarity beats raw speed.
        // First half open play.
        DB::statement(<<<SQL
            UPDATE match_events
            SET phase = 'first_half'
            WHERE minute <= 45
        SQL);

        // Second half open play.
        DB::statement(<<<SQL
            UPDATE match_events
            SET phase = 'second_half'
            WHERE minute BETWEEN 46 AND 90
        SQL);

        // 91..93 in non-ET matches → second half stoppage (minute=90, stoppage=N).
        DB::statement(<<<SQL
            UPDATE match_events e
            SET phase = 'second_half_stoppage',
                stoppage_minute = e.minute - 90,
                minute = 90
            FROM game_matches m
            WHERE e.game_match_id = m.id
              AND e.minute BETWEEN 91 AND 93
              AND COALESCE(m.is_extra_time, false) = false
        SQL);

        // 91..93 in ET matches → ET first half (current ET generator started
        // at minute 91; their persisted home_score_et/away_score_et already
        // counted these as ET goals, so this preserves aggregate consistency).
        DB::statement(<<<SQL
            UPDATE match_events e
            SET phase = 'et_first_half'
            FROM game_matches m
            WHERE e.game_match_id = m.id
              AND e.minute BETWEEN 91 AND 93
              AND COALESCE(m.is_extra_time, false) = true
        SQL);

        // 94..105 → ET first half open play.
        DB::statement(<<<SQL
            UPDATE match_events
            SET phase = 'et_first_half'
            WHERE minute BETWEEN 94 AND 105
        SQL);

        // 106..120 → ET second half open play.
        DB::statement(<<<SQL
            UPDATE match_events
            SET phase = 'et_second_half'
            WHERE minute BETWEEN 106 AND 120
        SQL);

        // > 120 → ET second half stoppage (rare/none historically).
        DB::statement(<<<SQL
            UPDATE match_events
            SET phase = 'et_second_half_stoppage',
                stoppage_minute = minute - 120,
                minute = 120
            WHERE minute > 120
        SQL);

        // Defensive: minute=0 shouldn't exist, but if it does, label as FH.
        DB::statement(<<<SQL
            UPDATE match_events SET phase = 'first_half' WHERE phase IS NULL
        SQL);

        // 4. NOT NULL + index.
        Schema::table('match_events', function (Blueprint $table) {
            $table->string('phase', 24)->nullable(false)->change();
            $table->index(['game_match_id', 'phase'], 'match_events_game_match_phase_idx');
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropIndex('match_events_game_match_phase_idx');
            $table->dropColumn(['phase', 'stoppage_minute']);
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn([
                'first_half_stoppage',
                'second_half_stoppage',
                'et_first_half_stoppage',
                'et_second_half_stoppage',
            ]);
        });
    }
};
