<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add annual wage field to game_players table.
     *
     * Wages are stored in cents to avoid floating point issues.
     * Example: €2,500,000/year = 250,000,000 cents
     */
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->unsignedBigInteger('annual_wage')->default(0)->after('contract_until');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn('annual_wage');
        });
    }
};
