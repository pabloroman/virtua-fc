<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_stadiums', function (Blueprint $table) {
            // Current in-game display name for the stadium. Set by a manual
            // rename or by an active naming-rights deal; null means fall back
            // to the control-plane Team.stadium_name.
            $table->string('stadium_name')->nullable()->after('team_id');

            // Season of the most recent MANUAL rename, enforcing the
            // once-per-season limit. Naming-rights deals don't touch this.
            $table->integer('name_changed_season')->nullable()->after('stadium_name');
        });
    }

    public function down(): void
    {
        Schema::table('game_stadiums', function (Blueprint $table) {
            $table->dropColumn(['stadium_name', 'name_changed_season']);
        });
    }
};
