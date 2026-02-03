<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_finances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id')->unique();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();

            // Current balance (can be negative for debt)
            $table->bigInteger('balance')->default(0);

            // Budget allocations
            $table->bigInteger('wage_budget')->default(0);
            $table->bigInteger('transfer_budget')->default(0);

            // Season revenue tracking
            $table->bigInteger('tv_revenue')->default(0);
            $table->bigInteger('performance_bonus')->default(0);
            $table->bigInteger('cup_bonus')->default(0);
            $table->bigInteger('total_revenue')->default(0);

            // Season expense tracking
            $table->bigInteger('wage_expense')->default(0);
            $table->bigInteger('transfer_expense')->default(0);
            $table->bigInteger('total_expense')->default(0);

            // Net result for the season
            $table->bigInteger('season_profit_loss')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_finances');
    }
};
