<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_player_templates', function (Blueprint $table) {
            $table->id();
            $table->string('season');
            $table->uuid('player_id');
            $table->uuid('team_id');

            $table->unsignedSmallInteger('number')->nullable();
            $table->string('position');
            $table->string('market_value')->nullable();
            $table->unsignedBigInteger('market_value_cents')->default(0);
            $table->date('contract_until')->nullable();
            $table->unsignedBigInteger('annual_wage')->default(0);

            $table->unsignedTinyInteger('fitness')->default(80);
            $table->unsignedTinyInteger('morale')->default(80);
            $table->unsignedTinyInteger('durability')->default(50);

            $table->unsignedTinyInteger('game_technical_ability')->nullable();
            $table->unsignedTinyInteger('game_physical_ability')->nullable();
            $table->unsignedTinyInteger('potential')->nullable();
            $table->unsignedTinyInteger('potential_low')->nullable();
            $table->unsignedTinyInteger('potential_high')->nullable();

            $table->foreign('player_id')->references('id')->on('players');
            $table->foreign('team_id')->references('id')->on('teams');

            $table->index('season');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_templates');
    }
};
