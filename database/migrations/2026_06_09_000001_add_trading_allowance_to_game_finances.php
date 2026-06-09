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
            // Nullable on purpose: NULL means "not settled / pre-feature", which
            // the trailing average skips — a real settled break-even season is a
            // genuine 0. A default of 0 would make existing saves' back-dated
            // rows look like break-even seasons and dilute the average for years.
            $table->bigInteger('net_transfer_result')->nullable()->after('actual_transfer_income');

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
