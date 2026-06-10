<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('season_ticket_pricings', function (Blueprint $table) {
            // The global pricing preset the user picked for the season
            // (accessible / standard / premium). Replaces per-area free-number
            // pricing — one legible choice that scales every area's baseline.
            // Existing rows (priced before presets) default to 'standard'.
            $table->string('pricing_preset', 20)->default('standard')->after('total_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('season_ticket_pricings', function (Blueprint $table) {
            $table->dropColumn('pricing_preset');
        });
    }
};
