<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates players (reference) and game_players (game-scoped) tables.
     * Players contain static biographical data.
     * GamePlayers contain all dynamic career data that can change during a game.
     */
    public function up(): void
    {
        // ===================
        // REFERENCE TABLE
        // ===================

        // Players (static biographical data + base abilities)
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transfermarkt_id')->unique();
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->json('nationality')->nullable(); // Array of countries
            $table->string('height')->nullable(); // "1,88m"
            $table->enum('foot', ['left', 'right', 'both'])->nullable();
            $table->unsignedTinyInteger('technical_ability')->default(50); // 0-100, slow-changing
            $table->unsignedTinyInteger('physical_ability')->default(50); // 0-100, slow-changing
            $table->timestamps();

            $table->index('name');
        });

        // ===================
        // GAME-SCOPED TABLE
        // ===================

        // Game Players (all dynamic career data)
        Schema::create('game_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('player_id');
            $table->uuid('team_id'); // Current team (can change via transfer)

            // Contract & transfer data (can change during game)
            $table->string('position'); // Goalkeeper, Centre-Back, etc.
            $table->string('market_value')->nullable(); // "€28.00m"
            $table->unsignedBigInteger('market_value_cents')->default(0); // Parsed numeric
            $table->date('contract_until')->nullable();
            $table->string('signed_from')->nullable();
            $table->date('joined_on')->nullable();

            // Dynamic attributes (change frequently during game)
            $table->unsignedTinyInteger('fitness')->default(100); // 0-100
            $table->unsignedTinyInteger('morale')->default(70); // 0-100
            $table->date('injury_until')->nullable();
            $table->string('injury_type')->nullable();
            $table->unsignedInteger('suspended_until_matchday')->nullable(); // Matchday when suspension ends

            // Season stats
            $table->unsignedSmallInteger('appearances')->default(0);
            $table->unsignedSmallInteger('goals')->default(0);
            $table->unsignedSmallInteger('own_goals')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('yellow_cards')->default(0);
            $table->unsignedSmallInteger('red_cards')->default(0);

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('players');
            $table->foreign('team_id')->references('id')->on('teams');

            $table->unique(['game_id', 'player_id']);
            $table->index(['game_id', 'team_id']);
            $table->index(['game_id', 'team_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('players');
    }
};
