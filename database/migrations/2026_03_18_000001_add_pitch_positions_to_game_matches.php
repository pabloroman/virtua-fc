<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->json('home_pitch_positions')->nullable()->after('home_defensive_line');
            $table->json('away_pitch_positions')->nullable()->after('away_defensive_line');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['home_pitch_positions', 'away_pitch_positions']);
        });
    }
};
