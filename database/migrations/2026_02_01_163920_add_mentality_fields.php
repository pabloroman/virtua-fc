<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add default mentality to games table
        Schema::table('games', function (Blueprint $table) {
            $table->string('default_mentality')->default('balanced')->after('default_lineup');
        });

        // Add mentality columns to game_matches table
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('home_mentality')->nullable()->after('away_formation');
            $table->string('away_mentality')->nullable()->after('home_mentality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('default_mentality');
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['home_mentality', 'away_mentality']);
        });
    }
};
