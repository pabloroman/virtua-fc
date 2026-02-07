<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // transfer_offers: queried by offer_type + status in ShowTransfers, ShowContracts
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->index(['game_id', 'offer_type', 'status']);
            $table->index(['game_id', 'direction', 'status']);
        });

        // game_players: queried by transfer_status in ShowTransfers for listed players
        Schema::table('game_players', function (Blueprint $table) {
            $table->index(['game_id', 'team_id', 'transfer_status']);
        });

        // game_matches: queried by competition + played for team form lookups
        Schema::table('game_matches', function (Blueprint $table) {
            $table->index(['game_id', 'competition_id', 'played']);
        });

        // financial_transactions: queried by category in ShowPreseason/ShowFinances
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->index(['game_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'offer_type', 'status']);
            $table->dropIndex(['game_id', 'direction', 'status']);
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'team_id', 'transfer_status']);
        });

        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'competition_id', 'played']);
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'category']);
        });
    }
};
