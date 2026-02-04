<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_suspensions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_player_id')->constrained()->cascadeOnDelete();
            $table->string('competition_id');
            $table->unsignedTinyInteger('matches_remaining');
            $table->timestamps();

            $table->unique(['game_player_id', 'competition_id']);
            $table->index(['game_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_suspensions');
    }
};
