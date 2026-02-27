<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make team_id nullable on game_players to support free agents.
 *
 * When an AI player's contract expires and isn't renewed, they become
 * a free agent (team_id = NULL) instead of being deleted. They can then
 * be signed by an AI team when the transfer window closes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->uuid('team_id')->nullable(false)->change();
        });
    }
};
