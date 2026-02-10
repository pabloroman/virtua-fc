<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->bigInteger('projected_subsidy_revenue')->default(0)->after('projected_commercial_revenue');
            $table->bigInteger('actual_subsidy_revenue')->default(0)->after('actual_commercial_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn(['projected_subsidy_revenue', 'actual_subsidy_revenue']);
        });
    }
};