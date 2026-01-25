<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cup Round Templates - static configuration for each round
        Schema::create('cup_round_templates', function (Blueprint $table) {
            $table->id();
            $table->string('competition_id', 10);
            $table->string('season', 10);
            $table->unsignedTinyInteger('round_number');
            $table->string('round_name');
            $table->enum('type', ['one_leg', 'two_leg']);
            $table->date('first_leg_date');
            $table->date('second_leg_date')->nullable();
            $table->unsignedSmallInteger('teams_entering')->default(0);

            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->unique(['competition_id', 'season', 'round_number']);
        });

        // Cup Ties - game-specific matchups (one per pairing)
        Schema::create('cup_ties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('competition_id', 10);
            $table->unsignedTinyInteger('round_number');
            $table->uuid('home_team_id');
            $table->uuid('away_team_id');
            $table->uuid('first_leg_match_id')->nullable();
            $table->uuid('second_leg_match_id')->nullable();
            $table->uuid('winner_id')->nullable();
            $table->boolean('completed')->default(false);
            $table->json('resolution')->nullable();
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->foreign('home_team_id')->references('id')->on('teams');
            $table->foreign('away_team_id')->references('id')->on('teams');
            $table->foreign('winner_id')->references('id')->on('teams');

            $table->index(['game_id', 'competition_id', 'round_number']);
        });

        // Add cup_tie_id to game_matches after cup_ties exists
        Schema::table('game_matches', function (Blueprint $table) {
            $table->uuid('cup_tie_id')->nullable()->after('played_at');
            $table->boolean('is_extra_time')->default(false)->after('cup_tie_id');
            $table->unsignedTinyInteger('home_score_et')->nullable()->after('is_extra_time');
            $table->unsignedTinyInteger('away_score_et')->nullable()->after('home_score_et');
            $table->unsignedTinyInteger('home_score_penalties')->nullable()->after('away_score_et');
            $table->unsignedTinyInteger('away_score_penalties')->nullable()->after('home_score_penalties');

            $table->foreign('cup_tie_id')->references('id')->on('cup_ties')->onDelete('set null');
        });

        // Add cup tracking to games
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedTinyInteger('cup_round')->default(0)->after('current_matchday');
            $table->boolean('cup_eliminated')->default(false)->after('cup_round');
        });

        // Add entry_round to competition_teams for cup entry points
        Schema::table('competition_teams', function (Blueprint $table) {
            $table->unsignedTinyInteger('entry_round')->default(1)->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('competition_teams', function (Blueprint $table) {
            $table->dropColumn('entry_round');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['cup_round', 'cup_eliminated']);
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropForeign(['cup_tie_id']);
            $table->dropColumn([
                'cup_tie_id',
                'is_extra_time',
                'home_score_et',
                'away_score_et',
                'home_score_penalties',
                'away_score_penalties',
            ]);
        });

        Schema::dropIfExists('cup_ties');
        Schema::dropIfExists('cup_round_templates');
    }
};
