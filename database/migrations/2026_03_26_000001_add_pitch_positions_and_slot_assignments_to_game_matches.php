<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->json('home_pitch_positions')->nullable()->after('away_defensive_line');
            $table->json('away_pitch_positions')->nullable()->after('home_pitch_positions');
            $table->json('home_slot_assignments')->nullable()->after('away_pitch_positions');
            $table->json('away_slot_assignments')->nullable()->after('home_slot_assignments');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn([
                'home_pitch_positions',
                'away_pitch_positions',
                'home_slot_assignments',
                'away_slot_assignments',
            ]);
        });
    }
};
