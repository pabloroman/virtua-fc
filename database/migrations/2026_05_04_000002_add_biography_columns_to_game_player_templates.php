<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 of the player-data planes refactor: add nullable biographical
     * columns to game_player_templates. Once populated, templates become the
     * canonical real-world roster source and replace the players table
     * entirely (the table itself moves to the control plane in a later phase).
     *
     * Columns are nullable here; backfill from players lands in Phase 2.
     */
    public function up(): void
    {
        Schema::table('game_player_templates', function (Blueprint $table) {
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
        Schema::table('game_player_templates', function (Blueprint $table) {
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
