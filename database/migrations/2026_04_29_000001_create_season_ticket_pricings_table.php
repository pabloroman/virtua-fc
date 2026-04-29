<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-season pricing for the user's team season tickets. One row per
     * (game, season). Areas are stored as JSON because the count and labels
     * are derived from stadium capacity at write time. total_sold and
     * total_revenue are aggregates so attendance/revenue lookups don't
     * have to re-walk the JSON each match.
     */
    public function up(): void
    {
        Schema::create('season_ticket_pricings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->unsignedSmallInteger('season');

            $table->json('areas');
            $table->unsignedInteger('total_capacity');
            $table->unsignedInteger('total_sold');
            $table->unsignedBigInteger('total_revenue');
            $table->boolean('is_default')->default(false);

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->unique(['game_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_ticket_pricings');
    }
};
