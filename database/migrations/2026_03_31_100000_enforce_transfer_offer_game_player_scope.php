<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GAME_PLAYERS_COMPOSITE_UNIQUE = 'game_players_id_game_id_unique_idx';

    private const TRANSFER_OFFERS_GAME_PLAYER_GAME_INDEX = 'transfer_offers_game_player_game_idx';

    private const TRANSFER_OFFERS_GAME_PLAYER_GAME_FOREIGN = 'transfer_offers_game_player_game_foreign';

    public function up(): void
    {
        $hasInconsistentOffers = DB::table('transfer_offers')
            ->join('game_players', 'game_players.id', '=', 'transfer_offers.game_player_id')
            ->whereColumn('game_players.game_id', '!=', 'transfer_offers.game_id')
            ->exists();

        if ($hasInconsistentOffers) {
            throw new RuntimeException('Cannot enforce transfer offer game scope: inconsistent transfer offers already exist.');
        }

        Schema::table('game_players', function (Blueprint $table) {
            $table->unique(['id', 'game_id'], self::GAME_PLAYERS_COMPOSITE_UNIQUE);
        });

        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->dropForeign(['game_player_id']);
            $table->index(['game_player_id', 'game_id'], self::TRANSFER_OFFERS_GAME_PLAYER_GAME_INDEX);
            $table->foreign(['game_player_id', 'game_id'], self::TRANSFER_OFFERS_GAME_PLAYER_GAME_FOREIGN)
                ->references(['id', 'game_id'])
                ->on('game_players')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfer_offers', function (Blueprint $table) {
            $table->dropForeign(self::TRANSFER_OFFERS_GAME_PLAYER_GAME_FOREIGN);
            $table->dropIndex(self::TRANSFER_OFFERS_GAME_PLAYER_GAME_INDEX);
            $table->foreign('game_player_id')->references('id')->on('game_players')->cascadeOnDelete();
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropUnique(self::GAME_PLAYERS_COMPOSITE_UNIQUE);
        });
    }
};
