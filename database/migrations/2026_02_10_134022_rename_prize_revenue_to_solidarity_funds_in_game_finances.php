<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->renameColumn('projected_prize_revenue', 'projected_solidarity_funds_revenue');
            $table->renameColumn('actual_prize_revenue', 'actual_cup_bonus_revenue');
            $table->bigInteger('actual_solidarity_funds_revenue')->default(0)->after('actual_tv_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->renameColumn('projected_solidarity_funds_revenue', 'projected_prize_revenue');
            $table->renameColumn('actual_cup_bonus_revenue', 'actual_prize_revenue');
            $table->dropColumn('actual_solidarity_funds_revenue');
        });
    }
};