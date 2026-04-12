<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the sparse game_player_match_state satellite table.
     *
     * Holds the 13 hot-write columns that get updated on every matchday by
     * PlayerConditionService and MatchResultProcessor (fitness, morale, injury,
     * appearances, goals, assists, cards, GK stats). Only populated for
     * "active" players — those whose team belongs to a competition in the
     * game's country (La Liga, Segunda, Copa del Rey) or who become a
     * European opponent for a season the user qualifies for. Foreign
     * transfer-pool players have no row here at all.
     */
    public function up(): void
    {
        Schema::create('game_player_match_state', function (Blueprint $table) {
            $table->uuid('game_player_id')->primary();
            $table->unsignedTinyInteger('fitness')->default(80);
            $table->unsignedTinyInteger('morale')->default(80);
            $table->date('injury_until')->nullable();
            $table->string('injury_type')->nullable();
            $table->unsignedSmallInteger('appearances')->default(0);
            $table->unsignedSmallInteger('season_appearances')->default(0);
            $table->unsignedSmallInteger('goals')->default(0);
            $table->unsignedSmallInteger('own_goals')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('yellow_cards')->default(0);
            $table->unsignedSmallInteger('red_cards')->default(0);
            $table->unsignedInteger('goals_conceded')->default(0);
            $table->unsignedInteger('clean_sheets')->default(0);

            $table->foreign('game_player_id')
                ->references('id')
                ->on('game_players')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_match_state');
    }
};
