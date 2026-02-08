<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('game_id')->constrained();
            $table->foreignId('competition_id')->constrained();

            // Knock-off tournaments have pre-fixed fixtures but teams aren't known yet
            $table->foreignId('home_team_id')->nullable()->constrained('teams');
            $table->foreignId('away_team_id')->nullable()->constrained('teams');

            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();

            $table->dateTime('scheduled_date');
            $table->integer('round_number');
            $table->string('round_name');
            $table->boolean('played')->default(false);
        });

        Schema::create('standings', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('game_id')->constrained();
            $table->foreignId('team_id')->constrained();
            $table->foreignId('competition_id')->constrained();

            $table->unsignedSmallInteger('prev_position')->nullable();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('matches_played')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('wins')->default(0);
            $table->unsignedSmallInteger('draws')->default(0);
            $table->unsignedSmallInteger('loses')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
