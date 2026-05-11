<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates game_player_match_ratings: one row per (match, player) holding the
     * 1.0–10.0 rating shown on the post-match screen and the raw 0.7–1.3
     * performance modifier rolled by the simulator. Goals/assists/cards live in
     * match_events — this table only stores the derived score.
     */
    public function up(): void
    {
        Schema::create('game_player_match_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('game_match_id');
            $table->uuid('game_player_id');

            $table->decimal('rating', 3, 1);
            $table->decimal('performance_modifier', 4, 3);

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('game_match_id')->references('id')->on('game_matches')->onDelete('cascade');
            $table->foreign('game_player_id')->references('id')->on('game_players')->onDelete('cascade');

            $table->unique(['game_match_id', 'game_player_id'], 'gpmr_match_player_uniq');
            $table->index('game_player_id');
        });

        // Defence in depth for callers that bypass HasUuids (Model::insert bulk path).
        DB::statement('ALTER TABLE game_player_match_ratings ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_match_ratings');
    }
};
