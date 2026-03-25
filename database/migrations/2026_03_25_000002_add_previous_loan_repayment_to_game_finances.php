<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->bigInteger('previous_loan_repayment')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn('previous_loan_repayment');
        });
    }
};
