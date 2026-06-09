<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            // Per-season NET player-trading result (sales − purchases), windowed
            // to the season. Can be negative (a net buyer). Feeds the trailing
            // player-trading allowance ("plusvalías") that widens the salary cap.
            $table->bigInteger('net_transfer_result')->default(0)->after('actual_transfer_income');

            // Projected trailing player-trading allowance added to the cap base
            // (never to the surplus/budget). See config/finances.trading_allowance.
            $table->bigInteger('projected_trading_allowance')->default(0)->after('projected_naming_rights_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn(['net_transfer_result', 'projected_trading_allowance']);
        });
    }
};
