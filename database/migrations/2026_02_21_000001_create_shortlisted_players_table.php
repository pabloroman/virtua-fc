<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortlisted_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->date('added_at');

            $table->unique(['game_id', 'game_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortlisted_players');
    }
};
