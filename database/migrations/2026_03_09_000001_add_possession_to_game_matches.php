<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('home_possession')->nullable()->after('away_score_penalties');
            $table->unsignedTinyInteger('away_possession')->nullable()->after('home_possession');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn(['home_possession', 'away_possession']);
        });
    }
};
