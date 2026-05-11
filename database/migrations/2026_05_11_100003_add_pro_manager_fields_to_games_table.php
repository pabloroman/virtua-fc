<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro-manager-mode state on the game itself:
 *   - pending_team_switch holds the manager_job_offer the user has accepted
 *     for the upcoming season; ApplyPendingTeamSwitchProcessor consumes it.
 *   - fired_at_season_end records that the current tenure ended in dismissal,
 *     blocking "Start New Season" until an offer is taken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->uuid('pending_team_switch')->nullable();
            $table->boolean('fired_at_season_end')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['pending_team_switch', 'fired_at_season_end']);
        });
    }
};
