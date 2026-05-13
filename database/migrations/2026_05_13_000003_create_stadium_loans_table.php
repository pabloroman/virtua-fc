<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stadium_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->uuid('stadium_project_id');

            // Original principal in cents. Annual principal payment is a
            // flat principal / term_years; annual interest is on the
            // outstanding balance, so total annual payment is highest in
            // year 1 and declines over the life of the loan.
            $table->bigInteger('principal_cents');

            $table->unsignedTinyInteger('term_years');
            // Interest rate in basis points (e.g., 400 = 4.00%).
            $table->unsignedSmallInteger('interest_rate_bps');

            // Outstanding principal; decreases by principal/term_years each
            // season the loan is in service.
            $table->bigInteger('remaining_principal_cents');

            // First season a repayment is due (the season after commit).
            $table->integer('season_started');

            // 'active' until remaining_principal hits zero, then 'repaid'.
            $table->string('status', 20)->default('active');

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->index(['game_id', 'status']);
        });

        DB::statement('ALTER TABLE stadium_loans ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        Schema::dropIfExists('stadium_loans');
    }
};
