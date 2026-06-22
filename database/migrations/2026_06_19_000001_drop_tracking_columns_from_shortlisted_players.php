<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shortlisted_players', function (Blueprint $table) {
            $table->dropColumn(['intel_level', 'is_tracking', 'matchdays_tracked']);
        });

        // Clear transient tracking-intel notifications from existing saves — the
        // type no longer exists, so leaving them would orphan their navigation.
        DB::table('game_notifications')->where('type', 'tracking_intel_ready')->delete();
    }

    public function down(): void
    {
        Schema::table('shortlisted_players', function (Blueprint $table) {
            $table->tinyInteger('intel_level')->default(0);
            $table->boolean('is_tracking')->default(false);
            $table->smallInteger('matchdays_tracked')->default(0);
        });
    }
};
