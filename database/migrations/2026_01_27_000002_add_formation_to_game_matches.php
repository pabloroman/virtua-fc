<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->string('home_formation', 10)->nullable()->after('home_lineup');
            $table->string('away_formation', 10)->nullable()->after('away_lineup');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['home_formation', 'away_formation']);
        });
    }
};
