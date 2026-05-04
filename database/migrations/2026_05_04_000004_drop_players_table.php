<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7b — drop the players table and its FK constraints.
     *
     * Biographical fields have lived on game_players (Phase 1+) and
     * game_player_templates (Phase 1+) since the start of this refactor;
     * after Phase 7a all readers consume them locally and the only
     * remaining writers (PlayerGeneratorService, admin StorePlayerTemplate)
     * stop creating Player rows in the same PR that runs this migration.
     *
     * The `player_id` column stays on game_players and game_player_templates
     * as a soft identity UUID — required by the `(game_id, player_id)`
     * unique constraint and used by GamePlayerTemplateService to keep the
     * same real-world player stable across (season, team) templates via
     * UUIDv5 of the transfermarkt_id.
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
        });

        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->dropForeign(['player_id']);
        });

        Schema::dropIfExists('players');
    }

    public function down(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transfermarkt_id')->nullable()->unique();
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->json('nationality')->nullable();
            $table->string('height')->nullable();
            $table->enum('foot', ['left', 'right', 'both'])->nullable();
            $table->unsignedTinyInteger('overall_score')->default(50);

            $table->index('name');
        });

        Schema::table('game_players', function (Blueprint $table) {
            $table->foreign('player_id')->references('id')->on('players');
        });

        Schema::table('game_player_templates', function (Blueprint $table) {
            $table->foreign('player_id')->references('id')->on('players');
        });
    }
};
