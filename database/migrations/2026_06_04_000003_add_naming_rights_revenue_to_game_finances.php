<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            // Naming-rights income from an active stadium sponsorship deal.
            // Projected at the deal's expected fill; settled at actual fill.
            $table->bigInteger('projected_naming_rights_revenue')->default(0)->after('projected_commercial_revenue');
            $table->bigInteger('actual_naming_rights_revenue')->default(0)->after('actual_commercial_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn(['projected_naming_rights_revenue', 'actual_naming_rights_revenue']);
        });
    }
};
