<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_investments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->integer('season');

            // Available budget (projected surplus minus carried debt)
            $table->bigInteger('available_surplus')->default(0);

            // Youth Academy
            $table->bigInteger('youth_academy_amount')->default(0);
            $table->tinyInteger('youth_academy_tier')->default(1);

            // Medical & Sports Science
            $table->bigInteger('medical_amount')->default(0);
            $table->tinyInteger('medical_tier')->default(1);

            // Scouting Network
            $table->bigInteger('scouting_amount')->default(0);
            $table->tinyInteger('scouting_tier')->default(1);

            // Facilities
            $table->bigInteger('facilities_amount')->default(0);
            $table->tinyInteger('facilities_tier')->default(1);

            // Transfer Budget
            $table->bigInteger('transfer_budget')->default(0);

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->unique(['game_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_investments');
    }
};
