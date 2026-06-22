<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist each real-world player's Sofascore id alongside their Transfermarkt
     * id, linked via the data/{season}/people.csv crosswalk at template-creation
     * time. A stable external reference for downstream consumers (e.g. images,
     * stats). Nullable: the crosswalk doesn't cover everyone, and synthetic
     * (academy/AI) players have no Sofascore id.
     */
    public function up(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->string('sofascore_id')->nullable()->after('transfermarkt_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->dropColumn('sofascore_id');
        });
    }
};
