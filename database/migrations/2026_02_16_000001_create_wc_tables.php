<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wc_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identity
            $table->string('name');
            $table->string('short_name', 10);
            $table->char('country_code', 2);
            $table->string('confederation', 10);
            $table->string('image')->nullable();

            // Strength & seeding
            $table->unsignedTinyInteger('strength')->default(50);
            $table->unsignedTinyInteger('pot')->default(4);

            $table->unique('country_code');
        });

        Schema::create('wc_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wc_team_id');

            // Player identity
            $table->string('name');
            $table->date('date_of_birth')->nullable();
            $table->json('nationality')->nullable();
            $table->string('height')->nullable();
            $table->string('foot')->nullable();
            $table->string('position');
            $table->unsignedTinyInteger('number')->nullable();

            // Abilities
            $table->unsignedTinyInteger('technical_ability');
            $table->unsignedTinyInteger('physical_ability');

            $table->foreign('wc_team_id')->references('id')->on('wc_teams')->cascadeOnDelete();
            $table->index('wc_team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_players');
        Schema::dropIfExists('wc_teams');
    }
};
