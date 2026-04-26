<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_squad_career_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_player_id');
            $table->uuid('game_id');
            $table->uuid('team_id');
            $table->unsignedSmallInteger('joined_season');
            $table->string('joined_from')->nullable();
            $table->jsonb('season_stats')->default('{}');

            $table->unique('game_player_id');
            $table->index(['game_id', 'team_id']);

            $table->foreign('game_player_id')
                ->references('id')->on('game_players')
                ->cascadeOnDelete();
            $table->foreign('game_id')
                ->references('id')->on('games')
                ->cascadeOnDelete();
            $table->foreign('team_id')
                ->references('id')->on('teams')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_squad_career_records');
    }
};
