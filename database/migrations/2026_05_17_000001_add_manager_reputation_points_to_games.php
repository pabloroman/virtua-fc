<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-game manager reputation, accumulated across the seasons of a
 * pro-manager career. Independent of club reputation, so a manager who
 * over-performs at a small club builds a personal stock that unlocks
 * offers above the current club's prestige band — modelling the
 * Bielsa / Klopp / Emery arc where the manager outgrows the team.
 *
 * Points map onto the same 5-tier scale as TeamReputation (local..elite)
 * with thresholds at 0/100/200/300/400, so the value drops directly into
 * JobOfferService::prestigeRank() as a parallel anchor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->integer('manager_reputation_points')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('manager_reputation_points');
        });
    }
};
