<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            // Seeded buyout clause (cents). Precomputed for ES-club templates
            // (es_floor_multiplier × market_value_cents); null otherwise. Copied
            // verbatim into game_players at game initialization.
            $table->unsignedBigInteger('release_clause')->nullable()->after('annual_wage');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->dropColumn('release_clause');
        });
    }
};
