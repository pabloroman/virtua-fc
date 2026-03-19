<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_trophies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained();
            $table->string('competition_id');
            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->string('season');
            $table->string('trophy_type'); // league, cup, european, supercup

            $table->unique(['game_id', 'competition_id', 'season']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_trophies');
    }
};
