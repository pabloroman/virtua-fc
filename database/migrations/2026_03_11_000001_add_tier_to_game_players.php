<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Market value thresholds in cents for each tier.
     * Must match PlayerTierService constants.
     */
    private const TIER_5_MIN = 50_000_000_00;  // €50M+
    private const TIER_4_MIN = 20_000_000_00;  // €20M+
    private const TIER_3_MIN =  5_000_000_00;  // €5M+
    private const TIER_2_MIN =  1_000_000_00;  // €1M+

    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->unsignedTinyInteger('tier')->default(1)->after('game_physical_ability');
            $table->index(['game_id', 'tier']);
        });

        // Backfill tiers for all existing game players
        DB::statement("
            UPDATE game_players SET tier = CASE
                WHEN market_value_cents >= " . self::TIER_5_MIN . " THEN 5
                WHEN market_value_cents >= " . self::TIER_4_MIN . " THEN 4
                WHEN market_value_cents >= " . self::TIER_3_MIN . " THEN 3
                WHEN market_value_cents >= " . self::TIER_2_MIN . " THEN 2
                ELSE 1
            END
        ");
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'tier']);
            $table->dropColumn('tier');
        });
    }
};
