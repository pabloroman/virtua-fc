<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pro-manager-mode job offers. An offer exists from the moment it is
 * generated (either at game start as `initial`, or end-of-season) until the
 * user resolves it. game_id is nullable so that the initial-offers flow can
 * stage offers before a Game record exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_job_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('game_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained();
            $table->string('competition_id')->nullable();
            $table->foreign('competition_id')->references('id')->on('competitions');

            $table->string('season')->nullable();
            $table->string('offer_type');   // initial | end_of_season | post_firing
            $table->string('status');       // pending | accepted | rejected | expired
            // String tier name ('local', 'modest', 'established', 'continental',
            // 'elite') — matches the canonical ClubProfile / TeamReputation
            // representation.
            $table->string('source_reputation_level')->nullable();
            $table->string('target_reputation_level');
            $table->date('created_on_game_date')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['game_id', 'season', 'status']);
        });

        // Defence in depth for bulk inserts that bypass HasUuids.
        DB::statement('ALTER TABLE manager_job_offers ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_job_offers');
    }
};
