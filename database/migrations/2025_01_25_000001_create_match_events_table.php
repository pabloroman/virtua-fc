<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates match_events table for tracking individual events during matches
     * (goals, own goals, assists, cards, injuries).
     */
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('game_match_id');
            $table->uuid('game_player_id');
            $table->uuid('team_id'); // For quick filtering by team

            $table->unsignedTinyInteger('minute'); // 1-90+ (can exceed 90 for stoppage time)
            $table->string('event_type', 20); // goal, own_goal, assist, yellow_card, red_card, injury
            $table->json('metadata')->nullable(); // Additional data (injury type, goal type, etc.)

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('game_match_id')->references('id')->on('game_matches')->onDelete('cascade');
            $table->foreign('game_player_id')->references('id')->on('game_players')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams');

            $table->index('game_match_id');
            $table->index('game_player_id');
            $table->index(['game_id', 'event_type']); // For queries like "all goals in this game"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
