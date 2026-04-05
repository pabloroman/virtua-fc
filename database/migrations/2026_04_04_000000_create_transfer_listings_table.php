<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('game_player_id');
            $table->uuid('team_id');
            $table->string('status');
            $table->date('listed_at');
            $table->bigInteger('asking_price')->nullable();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('game_player_id')->references('id')->on('game_players')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();

            $table->unique('game_player_id');
            $table->index(['game_id', 'status', 'team_id']);
        });

        // Migrate existing data from game_players to transfer_listings
        DB::statement("
            INSERT INTO transfer_listings (id, game_id, game_player_id, team_id, status, listed_at)
            SELECT gen_random_uuid(), game_id, id, team_id, transfer_status, transfer_listed_at::date
            FROM game_players
            WHERE transfer_status IS NOT NULL
              AND transfer_listed_at IS NOT NULL
        ");

        Schema::table('game_players', function (Blueprint $table) {
            $table->dropIndex('game_players_game_id_team_id_transfer_status_index');
            $table->dropColumn(['transfer_status', 'transfer_listed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->string('transfer_status')->nullable()->after('season_appearances');
            $table->timestamp('transfer_listed_at')->nullable()->after('transfer_status');
            $table->index(['game_id', 'team_id', 'transfer_status']);
        });

        // Migrate data back
        DB::statement("
            UPDATE game_players
            SET transfer_status = tl.status,
                transfer_listed_at = tl.listed_at
            FROM transfer_listings tl
            WHERE game_players.id = tl.game_player_id
        ");

        Schema::dropIfExists('transfer_listings');
    }
};
