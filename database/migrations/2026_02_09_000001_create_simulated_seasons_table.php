<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulated_seasons', function (Blueprint $table) {
            $table->id();
            $table->uuid('game_id');
            $table->string('season', 10);
            $table->string('competition_id', 10);
            $table->json('results');
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->unique(['game_id', 'season', 'competition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulated_seasons');
    }
};
