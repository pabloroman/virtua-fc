<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->index('mvp_player_id');
            $table->index('cup_tie_id');
        });

        Schema::table('game_transfers', function (Blueprint $table) {
            $table->index('from_team_id');
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->index('related_player_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->index('parent_team_id');
        });

        Schema::table('activation_events', function (Blueprint $table) {
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropIndex(['mvp_player_id']);
            $table->dropIndex(['cup_tie_id']);
        });

        Schema::table('game_transfers', function (Blueprint $table) {
            $table->dropIndex(['from_team_id']);
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex(['related_player_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['parent_team_id']);
        });

        Schema::table('activation_events', function (Blueprint $table) {
            $table->dropIndex(['game_id']);
        });
    }
};
