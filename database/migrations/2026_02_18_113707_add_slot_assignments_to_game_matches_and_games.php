<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->json('home_slot_assignments')->nullable()->after('home_lineup');
            $table->json('away_slot_assignments')->nullable()->after('away_lineup');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->json('default_slot_assignments')->nullable()->after('default_lineup');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['home_slot_assignments', 'away_slot_assignments']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('default_slot_assignments');
        });
    }
};
