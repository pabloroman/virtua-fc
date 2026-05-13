<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_stadiums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('team_id');

            // Snapshot of Team.stadium_seats at game creation. Team lives on
            // the control plane and is immutable per-game; base_capacity is
            // the per-game starting point for this team's stadium.
            $table->unsignedInteger('base_capacity');

            // Permanent supplementary stands ("gradas supletorias"). Capped
            // at 5,000 across all installations for the life of this stadium
            // (a rebuild folds existing supletorias into rebuilt_capacity
            // and resets this counter).
            $table->unsignedInteger('supplementary_seats')->default(0);

            // If set, replaces base_capacity after a stadium rebuild.
            $table->unsignedInteger('rebuilt_capacity')->nullable();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams');
            $table->unique(['game_id', 'team_id']);
        });

        DB::statement('ALTER TABLE game_stadiums ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_stadiums');
    }
};
