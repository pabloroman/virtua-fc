<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist each real-world player's EA Sports FC26 id alongside their
     * Transfermarkt id, linked via fuzzy name+team matching against the FC26
     * player database at template-creation time. A stable external reference
     * for downstream consumers (e.g. images, ratings). Nullable: the fuzzy
     * match doesn't cover everyone, and synthetic (academy/AI) players have no
     * FC26 id.
     */
    public function up(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->string('fc26_id')->nullable()->after('transfermarkt_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->dropColumn('fc26_id');
        });
    }
};
