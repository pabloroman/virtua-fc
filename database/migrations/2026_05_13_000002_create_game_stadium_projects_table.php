<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_stadium_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('team_id');

            // 'supplementary' or 'rebuild'.
            $table->string('type', 20);

            // Lifecycle:
            //  supplementary: 'in_progress' (commit_date → completion_date) → 'completed'
            //  rebuild:       'pending' (committed; construction hasn't started)
            //               → 'in_progress' (construction season; capacity at 40%)
            //               → 'completed' (rebuilt_capacity is live)
            $table->string('status', 20);

            // For 'supplementary': number of seats being added (≤ 5,000 with
            //   any existing supletorias).
            // For 'rebuild': the total target capacity of the new stadium.
            $table->unsignedInteger('target_capacity');

            $table->integer('committed_season');
            $table->date('committed_date');

            // For 'supplementary': committed_date + ~30 days.
            // For 'rebuild': filled in when construction completes.
            $table->date('completion_date')->nullable();

            // For 'rebuild' only: the season in which the new capacity goes
            // live (committed_season + 2; one off-season + one construction
            // season).
            $table->integer('completion_season')->nullable();

            $table->bigInteger('total_cost_cents');
            $table->string('financing', 20); // 'cash' | 'loan'
            // Cumulative cash paid by the club (excludes loan principal —
            // the bank pays that to the project escrow on the club's behalf).
            $table->bigInteger('paid_cents')->default(0);

            $table->uuid('stadium_loan_id')->nullable();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams');
            $table->index(['game_id', 'team_id', 'status']);
        });

        DB::statement('ALTER TABLE game_stadium_projects ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_stadium_projects');
    }
};
