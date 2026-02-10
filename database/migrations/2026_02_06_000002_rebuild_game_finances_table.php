<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old table and recreate with new structure
        Schema::dropIfExists('game_finances');

        Schema::create('game_finances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->integer('season');

            // Projections (calculated at pre-season)
            $table->integer('projected_position')->nullable();
            $table->bigInteger('projected_tv_revenue')->default(0);
            $table->bigInteger('projected_prize_revenue')->default(0);
            $table->bigInteger('projected_matchday_revenue')->default(0);
            $table->bigInteger('projected_commercial_revenue')->default(0);
            $table->bigInteger('projected_total_revenue')->default(0);
            $table->bigInteger('projected_wages')->default(0);
            $table->bigInteger('projected_surplus')->default(0);

            // Actuals (calculated at season end)
            $table->bigInteger('actual_tv_revenue')->default(0);
            $table->bigInteger('actual_prize_revenue')->default(0);
            $table->bigInteger('actual_matchday_revenue')->default(0);
            $table->bigInteger('actual_commercial_revenue')->default(0);
            $table->bigInteger('actual_transfer_income')->default(0);
            $table->bigInteger('actual_total_revenue')->default(0);
            $table->bigInteger('actual_wages')->default(0);
            $table->bigInteger('actual_surplus')->default(0);

            // Settlement
            $table->bigInteger('variance')->default(0); // actual_surplus - projected_surplus
            $table->bigInteger('carried_debt')->default(0); // debt from previous season

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->unique(['game_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_finances');

        // Recreate old structure if needed
        Schema::create('game_finances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('wage_budget')->default(0);
            $table->bigInteger('transfer_budget')->default(0);
            $table->bigInteger('tv_revenue')->default(0);
            $table->bigInteger('performance_bonus')->default(0);
            $table->bigInteger('cup_bonus')->default(0);
            $table->bigInteger('total_revenue')->default(0);
            $table->bigInteger('wage_expense')->default(0);
            $table->bigInteger('transfer_expense')->default(0);
            $table->bigInteger('total_expense')->default(0);
            $table->bigInteger('season_profit_loss')->default(0);

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
        });
    }
};
