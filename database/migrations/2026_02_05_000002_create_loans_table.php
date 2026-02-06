<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('game_player_id');
            $table->uuid('parent_team_id');
            $table->uuid('loan_team_id');

            $table->date('started_at');
            $table->date('return_at'); // Always June 30 of season
            $table->string('status')->default('active'); // active, completed

            $table->timestamps();

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->cascadeOnDelete();

            $table->foreign('game_player_id')
                ->references('id')
                ->on('game_players')
                ->cascadeOnDelete();

            $table->foreign('parent_team_id')
                ->references('id')
                ->on('teams');

            $table->foreign('loan_team_id')
                ->references('id')
                ->on('teams');

            $table->index(['game_id', 'status']);
            $table->index(['game_player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
