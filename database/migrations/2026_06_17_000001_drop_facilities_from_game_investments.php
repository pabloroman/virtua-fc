<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the standalone Facilities investment lever. Matchday revenue is now
 * driven entirely by the Stadium module (capacity, attendance, ticketing) and
 * commercial growth by the Commercial hub, so the facilities tier — which only
 * applied a 1.0–1.6× multiplier to matchday revenue — is redundant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_investments', function (Blueprint $table) {
            $table->dropColumn(['facilities_amount', 'facilities_tier']);
        });
    }

    public function down(): void
    {
        Schema::table('game_investments', function (Blueprint $table) {
            $table->bigInteger('facilities_amount')->default(0);
            $table->tinyInteger('facilities_tier')->default(1);
        });
    }
};
