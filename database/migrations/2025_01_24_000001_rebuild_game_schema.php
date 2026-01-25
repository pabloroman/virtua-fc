<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration rebuilds the game schema with proper UUID support
     * and correct table relationships.
     */
    public function up(): void
    {
        // Drop old tables in correct order (respecting foreign keys)
        Schema::dropIfExists('competition_entries');
        Schema::dropIfExists('standings');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('games');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('competitions');

        // ===================
        // REFERENCE TABLES
        // ===================

        // Competitions (static reference data)
        Schema::create('competitions', function (Blueprint $table) {
            $table->string('id', 10)->primary(); // 'ESP1', 'ESP2', 'COPA', etc.
            $table->string('name');
            $table->char('country', 2);
            $table->unsignedTinyInteger('tier')->default(1);
            $table->enum('type', ['league', 'cup', 'playoff', 'european'])->default('league');
            $table->string('season', 10)->default('2024'); // '2024', '2024-25'
        });

        // Teams (static reference data, seeded from JSON)
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID from JSON data
            $table->unsignedInteger('transfermarkt_id')->nullable()->index();
            $table->string('name');
            $table->string('official_name')->nullable();
            $table->char('country', 2)->default('ES');
            $table->string('image')->nullable();
            $table->string('stadium_name')->nullable();
            $table->unsignedInteger('stadium_seats')->default(0);
            $table->json('colors')->nullable();
            $table->string('current_market_value')->nullable();
            $table->date('founded_on')->nullable();
            $table->timestamps();
        });

        // Competition Teams (which teams in which competition per season)
        Schema::create('competition_teams', function (Blueprint $table) {
            $table->string('competition_id', 10);
            $table->uuid('team_id');
            $table->string('season', 10)->default('2024');
            $table->primary(['competition_id', 'team_id', 'season']);

            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->foreign('team_id')->references('id')->on('teams');
        });

        // Fixture Templates (master schedule from JSON, not game-specific)
        Schema::create('fixture_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('competition_id', 10);
            $table->string('season', 10)->default('2024');
            $table->unsignedSmallInteger('round_number');
            $table->unsignedSmallInteger('match_number')->nullable();
            $table->uuid('home_team_id');
            $table->uuid('away_team_id');
            $table->dateTime('scheduled_date');
            $table->string('location')->nullable();

            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->foreign('home_team_id')->references('id')->on('teams');
            $table->foreign('away_team_id')->references('id')->on('teams');

            $table->index(['competition_id', 'season', 'round_number']);
        });

        // ===================
        // GAME-SCOPED TABLES (Projections)
        // ===================

        // Games (event-sourced aggregate projection)
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('player_name');
            $table->uuid('team_id');
            $table->string('season', 10)->default('2024');
            $table->date('current_date')->nullable();
            $table->unsignedSmallInteger('current_matchday')->default(0);
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams');
        });

        // Game Matches (copied from fixture_templates when game starts)
        Schema::create('game_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('competition_id', 10);
            $table->unsignedSmallInteger('round_number');
            $table->string('round_name')->nullable();
            $table->uuid('home_team_id');
            $table->uuid('away_team_id');
            $table->dateTime('scheduled_date');
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->boolean('played')->default(false);
            $table->timestamp('played_at')->nullable();

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->foreign('home_team_id')->references('id')->on('teams');
            $table->foreign('away_team_id')->references('id')->on('teams');

            $table->index(['game_id', 'competition_id', 'round_number']);
            $table->index(['game_id', 'played']);
        });

        // Game Standings (league table per game)
        Schema::create('game_standings', function (Blueprint $table) {
            $table->id();
            $table->uuid('game_id');
            $table->string('competition_id', 10);
            $table->uuid('team_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('prev_position')->nullable();
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('won')->default(0);
            $table->unsignedSmallInteger('drawn')->default(0);
            $table->unsignedSmallInteger('lost')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->unsignedSmallInteger('points')->default(0);

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->foreign('team_id')->references('id')->on('teams');

            $table->unique(['game_id', 'competition_id', 'team_id']);
            $table->index(['game_id', 'competition_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_standings');
        Schema::dropIfExists('game_matches');
        Schema::dropIfExists('games');
        Schema::dropIfExists('fixture_templates');
        Schema::dropIfExists('competition_teams');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('competitions');
    }
};
