<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_competition_teams', function (Blueprint $table) {
            $table->uuid('game_id');
            $table->string('competition_id', 10);
            $table->uuid('team_id');
            $table->unsignedTinyInteger('entry_round')->default(1);

            $table->primary(['game_id', 'competition_id', 'team_id']);

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->foreign('team_id')->references('id')->on('teams');

            $table->index(['game_id', 'competition_id']);
            $table->index(['game_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_competition_teams');
    }
};
