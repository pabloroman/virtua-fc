<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 of the player-data planes refactor: add nullable biographical
     * columns to game_players so each game owns its players' biography
     * directly, without having to JOIN the control-plane players table.
     *
     * Columns are nullable here; backfill and dual-write land in later phases.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->string('transfermarkt_id')->nullable()->after('player_id');
            $table->string('name')->nullable()->after('transfermarkt_id');
            $table->date('date_of_birth')->nullable()->after('name');
            $table->json('nationality')->nullable()->after('date_of_birth');
            $table->string('height')->nullable()->after('nationality');
            $table->enum('foot', ['left', 'right', 'both'])->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn([
                'transfermarkt_id',
                'name',
                'date_of_birth',
                'nationality',
                'height',
                'foot',
            ]);
        });
    }
};
