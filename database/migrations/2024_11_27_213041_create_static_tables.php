<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('country', 3);
            $table->unsignedSmallInteger('tier');
            $table->char('code', 6);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('country', 3);
            $table->unsignedInteger('transfermarkt_id')->nullable();
            $table->string('official_name')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('initial_budget_amount')->nullable();
            $table->char('initial_budget_currency', 3)->nullable();
            $table->string('stadium_name')->nullable();
            $table->unsignedInteger('stadium_seats')->default(0);
            $table->date('founded_on')->nullable();
            $table->json('colors')->nullable();
            $table->unsignedBigInteger('initial_competition_id')->nullable();
            $table->unsignedBigInteger('parent_team_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
        Schema::dropIfExists('competitions');
    }
};
