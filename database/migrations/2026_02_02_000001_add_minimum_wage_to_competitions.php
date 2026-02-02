<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add minimum annual wage field to competitions table.
     *
     * Spanish football has regulated minimum salaries:
     * - La Liga: €200,000/year
     * - La Liga 2: €100,000/year
     * - Cups: inherit from team's primary league
     */
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            // Stored in cents (€200,000 = 20,000,000,00 cents)
            // Nullable for cups which don't have their own minimum
            $table->unsignedBigInteger('minimum_annual_wage')->nullable()->after('handler_type');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('minimum_annual_wage');
        });
    }
};
