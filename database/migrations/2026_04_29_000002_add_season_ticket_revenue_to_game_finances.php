<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks season ticket revenue separately from per-fixture matchday
     * revenue. Season tickets are pre-paid at season start and locked, so
     * projected and actual stay aligned (no variance) — settlement still
     * mirrors them for symmetry with the rest of the finances row.
     */
    public function up(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->unsignedBigInteger('projected_season_ticket_revenue')->default(0)->after('projected_matchday_revenue');
            $table->unsignedBigInteger('actual_season_ticket_revenue')->default(0)->after('actual_matchday_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('game_finances', function (Blueprint $table) {
            $table->dropColumn(['projected_season_ticket_revenue', 'actual_season_ticket_revenue']);
        });
    }
};
