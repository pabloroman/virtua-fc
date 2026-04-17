<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-layer loyalty stat on TeamReputation, parallel to the
 * base_reputation_level / reputation_points pair already on the table:
 *
 *   - base_loyalty   — the seeded cultural anchor, copied from
 *                      ClubProfile.fan_loyalty at game start. Never moves.
 *   - loyalty_points — the current value. Starts equal to base_loyalty and
 *                      drifts with outcomes via FanLoyaltyUpdateProcessor.
 *                      Clamped so it can't fall more than
 *                      TeamReputation::MAX_LOYALTY_DROP_BELOW_BASE below
 *                      base_loyalty — the "Newcastle doesn't lose its
 *                      fans in the Championship" floor.
 *
 * Pre-existing TeamReputation rows (games started before this branch) are
 * backfilled from ClubProfile.fan_loyalty × 10. New games seed via
 * SetupNewGame and write real values immediately, so the column default
 * is only a placeholder for the brief moment between insert and assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_reputations', function (Blueprint $table) {
            $table->unsignedTinyInteger('base_loyalty')->default(50)->after('reputation_points');
            $table->unsignedTinyInteger('loyalty_points')->default(50)->after('base_loyalty');
        });

        // Backfill from the curated 0-10 anchor on ClubProfile, scaled ×10
        // to the internal 0-100 range. Clamped to [0, 100] so any historical
        // ClubProfile.fan_loyalty values left over from earlier scale
        // experiments (e.g. 0-100 absolute) don't overflow.
        DB::statement(<<<SQL
            UPDATE team_reputations tr
            SET base_loyalty   = LEAST(100, GREATEST(0, COALESCE(cp.fan_loyalty, 5) * 10)),
                loyalty_points = LEAST(100, GREATEST(0, COALESCE(cp.fan_loyalty, 5) * 10))
            FROM club_profiles cp
            WHERE tr.team_id = cp.team_id
        SQL);
    }

    public function down(): void
    {
        Schema::table('team_reputations', function (Blueprint $table) {
            $table->dropColumn(['base_loyalty', 'loyalty_points']);
        });
    }
};
