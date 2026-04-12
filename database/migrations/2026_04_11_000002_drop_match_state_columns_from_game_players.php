<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the 13 hot-write columns from game_players that are now stored
     * in the game_player_match_state satellite table. Reads are mediated
     * by the GamePlayer accessor delegates so no application code should
     * notice the columns are gone.
     *
     * In production this runs strictly AFTER the
     * app:backfill-game-player-match-state command has copied existing
     * values out of game_players for every legacy game.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn([
                'fitness',
                'morale',
                'injury_until',
                'injury_type',
                'appearances',
                'season_appearances',
                'goals',
                'own_goals',
                'assists',
                'yellow_cards',
                'red_cards',
                'goals_conceded',
                'clean_sheets',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
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
        });
    }
};
