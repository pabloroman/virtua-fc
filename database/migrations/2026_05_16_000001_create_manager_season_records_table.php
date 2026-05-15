<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-season snapshot of a pro-manager's tenure: one row per season
 * managed, capturing the team, league, goal that was set, and how it
 * turned out. ManagerJobHistory tracks tenure spans (possibly several
 * seasons each); this table is the season-by-season detail used by the
 * career history page. Written by SnapshotManagerSeasonRecordProcessor
 * during the closing pipeline, while $game->season_goal and the final
 * GameStanding rows still reference the just-finished season.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_season_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained();
            $table->string('competition_id');
            $table->foreign('competition_id')->references('id')->on('competitions');

            // Raw year string matching games.season / manager_job_histories.season_start.
            $table->string('season');

            $table->string('season_goal')->nullable();
            $table->string('season_goal_label')->nullable();
            $table->smallInteger('final_position')->nullable();
            $table->boolean('goal_achieved')->nullable();
            $table->string('goal_grade')->nullable();
            $table->string('end_reason')->nullable();

            $table->timestamp('recorded_at')->useCurrent();

            $table->unique(['game_id', 'user_id', 'season'], 'mgr_season_records_unique');
            $table->index(['user_id']);
            $table->index(['game_id', 'season']);
        });

        DB::statement('ALTER TABLE manager_season_records ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_season_records');
    }
};
