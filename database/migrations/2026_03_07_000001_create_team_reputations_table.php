<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_reputations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('team_id');
            $table->string('reputation_level');      // current effective tier
            $table->string('base_reputation_level');  // seeded tier (floor reference)
            $table->integer('reputation_points');     // numeric score driving tier

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->unique(['game_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_reputations');
    }
};
