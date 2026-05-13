<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            // Year-N instalment of every active stadium loan, computed at
            // season projection time. Flows into available_surplus the same
            // way previous_loan_repayment does for the single-season budget
            // loan, so the user can't earmark this cash for transfers.
            $table->bigInteger('projected_stadium_debt_service')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn('projected_stadium_debt_service');
        });
    }
};
