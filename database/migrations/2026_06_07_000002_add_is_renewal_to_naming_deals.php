<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_stadium_naming_deals', function (Blueprint $table) {
            // Marks a pending offer minted as an incumbent renewal (same sponsor
            // keeping the name) rather than a fresh sponsor bid. Renewals carry
            // no fan-loyalty shock on acceptance, since the stadium name does
            // not change. See NamingRightsService::rolloverForNewSeason.
            $table->boolean('is_renewal')->default(false)->after('contract_seasons');
        });
    }

    public function down(): void
    {
        Schema::table('game_stadium_naming_deals', function (Blueprint $table) {
            $table->dropColumn('is_renewal');
        });
    }
};
