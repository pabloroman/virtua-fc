<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // game_matches: team-specific lookups filtered by played status
        // Used by MatchdayOrchestrator EXISTS checks (~2x per batch iteration)
        Schema::table('game_matches', function (Blueprint $table) {
            $table->index(['game_id', 'played', 'home_team_id']);
            $table->index(['game_id', 'played', 'away_team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'played', 'home_team_id']);
            $table->dropIndex(['game_id', 'played', 'away_team_id']);
        });
    }
};
