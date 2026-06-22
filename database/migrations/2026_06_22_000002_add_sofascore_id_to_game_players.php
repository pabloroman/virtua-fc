<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carry the Sofascore id from game_player_templates onto game_players when a
     * game is set up, mirroring the transfermarkt_id biography column. Nullable
     * for the same reasons as on the templates table.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->string('sofascore_id')->nullable()->after('transfermarkt_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('sofascore_id');
        });
    }
};
