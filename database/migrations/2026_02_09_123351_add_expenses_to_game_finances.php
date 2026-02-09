<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->bigInteger('projected_operating_expenses')->default(0)->after('projected_wages');
            $table->bigInteger('projected_taxes')->default(0)->after('projected_operating_expenses');
            $table->bigInteger('actual_operating_expenses')->default(0)->after('actual_wages');
            $table->bigInteger('actual_taxes')->default(0)->after('actual_operating_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn([
                'projected_operating_expenses',
                'projected_taxes',
                'actual_operating_expenses',
                'actual_taxes',
            ]);
        });
    }
};
