<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenure log for the pro-manager career: one row per (game, team) stint,
 * closed off with an end_reason when the user moves on or is fired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_job_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained();
            $table->string('competition_id');
            $table->foreign('competition_id')->references('id')->on('competitions');

            $table->string('season_start');
            $table->string('season_end')->nullable();
            $table->string('end_reason'); // left_voluntarily | fired | still_active

            $table->index(['game_id', 'season_start']);
            $table->index(['user_id']);
        });

        DB::statement('ALTER TABLE manager_job_histories ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_job_histories');
    }
};
