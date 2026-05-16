<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro-manager-mode state on the game itself:
 *   - pending_team_switch holds the manager_job_offer the user has accepted
 *     for the upcoming season; ApplyPendingTeamSwitchProcessor consumes it.
 *
 * Whether the manager was fired at season end is derived on read via
 * Game::wasFiredThisSeason() from the existence of POST_FIRING offer rows
 * — no denormalised flag needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->uuid('pending_team_switch')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('pending_team_switch');
        });
    }
};
