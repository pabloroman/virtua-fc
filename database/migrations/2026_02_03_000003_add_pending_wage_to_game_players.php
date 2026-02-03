<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            // Stores the new wage that will take effect at end of season
            $table->unsignedBigInteger('pending_annual_wage')->nullable()->after('annual_wage');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('pending_annual_wage');
        });
    }
};
