<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->integer('season');
            $table->bigInteger('amount'); // Principal in cents
            $table->integer('interest_rate'); // Stored as basis points (e.g., 1500 = 15%)
            $table->bigInteger('repayment_amount'); // amount × (1 + interest_rate/10000) in cents
            $table->string('status', 20)->default('active'); // active, repaid

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->index(['game_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_loans');
    }
};
