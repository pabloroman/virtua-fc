<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('team_id');

            // Player identity
            $table->string('name');
            $table->json('nationality')->nullable();
            $table->date('date_of_birth');
            $table->string('position');

            // Abilities & potential
            $table->unsignedTinyInteger('technical_ability');
            $table->unsignedTinyInteger('physical_ability');
            $table->unsignedTinyInteger('potential');
            $table->unsignedTinyInteger('potential_low');
            $table->unsignedTinyInteger('potential_high');

            // When this prospect appeared
            $table->date('appeared_at');

            $table->index(['game_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_players');
    }
};
