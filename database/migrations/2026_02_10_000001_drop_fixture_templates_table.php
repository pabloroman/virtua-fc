<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fixture_templates');
    }

    public function down(): void
    {
        Schema::create('fixture_templates', function ($table) {
            $table->uuid('id')->primary();
            $table->string('competition_id');
            $table->string('season');
            $table->integer('round_number');
            $table->integer('match_number');
            $table->uuid('home_team_id');
            $table->uuid('away_team_id');
            $table->dateTime('scheduled_date');
            $table->string('location')->nullable();

            $table->foreign('competition_id')->references('id')->on('competitions');
            $table->foreign('home_team_id')->references('id')->on('teams');
            $table->foreign('away_team_id')->references('id')->on('teams');

            $table->index(['competition_id', 'season', 'round_number']);
        });
    }
};
