<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');

            $table->string('status')->default('searching'); // searching, completed, cancelled
            $table->json('filters'); // {position, league, age_min, age_max, max_budget}
            $table->tinyInteger('weeks_total');
            $table->tinyInteger('weeks_remaining');
            $table->json('player_ids')->nullable(); // Array of game_player_id results

            $table->timestamps();

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->cascadeOnDelete();

            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_reports');
    }
};
