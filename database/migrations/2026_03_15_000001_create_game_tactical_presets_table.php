<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_tactical_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();

            $table->string('name', 30);
            $table->tinyInteger('sort_order');
            $table->string('formation', 10);
            $table->json('lineup');
            $table->json('slot_assignments')->nullable();
            $table->json('pitch_positions')->nullable();
            $table->string('mentality');
            $table->string('playing_style');
            $table->string('pressing');
            $table->string('defensive_line');

            $table->unique(['game_id', 'sort_order']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_tactical_presets');
    }
};
