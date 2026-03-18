<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->char('mvp_player_id', 36)->nullable()->after('away_possession');
            $table->foreign('mvp_player_id')->references('id')->on('game_players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropForeign(['mvp_player_id']);
            $table->dropColumn('mvp_player_id');
        });
    }
};
