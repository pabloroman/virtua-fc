<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams');
            $table->string('competition_id');
            $table->string('share_token', 12)->unique();
            $table->string('result_label'); // champion, runner_up, semi_finalist, etc.
            $table->json('stats'); // {played, won, drawn, lost, goals_for, goals_against}
            $table->json('squad_player_ids'); // array of transfermarkt_ids
            $table->json('squad_highlights'); // {bold_picks: [...], omissions: [...], top_scorer: {...}}
            $table->timestamps();

            $table->index('share_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_challenges');
    }
};
