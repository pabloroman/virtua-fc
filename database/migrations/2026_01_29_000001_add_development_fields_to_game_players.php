<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds player development fields to game_players table:
     * - Game-specific abilities that can deviate from Player reference over time
     * - Potential ability (hidden true value + visible scouted range)
     * - Season appearances tracking for development bonuses
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            // Game-scoped overall ability (evolves per game from the Player reference)
            $table->unsignedTinyInteger('overall_score')->nullable()->after('morale');

            // Potential (hidden true value + revealed range)
            $table->unsignedTinyInteger('potential')->nullable()->after('overall_score');
            $table->unsignedTinyInteger('potential_low')->nullable()->after('potential');
            $table->unsignedTinyInteger('potential_high')->nullable()->after('potential_low');

            // Season appearances tracking for development bonuses
            $table->unsignedSmallInteger('season_appearances')->default(0)->after('potential_high');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn([
                'overall_score',
                'potential',
                'potential_low',
                'potential_high',
                'season_appearances',
            ]);
        });
    }
};
