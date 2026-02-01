<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('season_archives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->string('season');

            // Aggregated data (always queryable)
            $table->json('final_standings');
            $table->json('player_season_stats');
            $table->json('season_awards');

            // Lightweight match results
            $table->json('match_results');

            // Compressed detailed events (gzipped JSON)
            $table->binary('match_events_archive')->nullable();

            $table->timestamps();

            $table->unique(['game_id', 'season']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('season_archives');
    }
};
