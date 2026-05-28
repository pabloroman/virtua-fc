<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the UEFA stadium category to the per-game stadium row.
     *
     *   base_uefa_level    — snapshot of Team.uefa_stadium_category at
     *                        game creation. Immutable for the life of the
     *                        save (mirrors how base_capacity works).
     *   rebuilt_uefa_level — set when the user completes a UEFA-upgrade
     *                        project. Null = still at base level.
     *
     * Effective level = rebuilt_uefa_level ?? base_uefa_level.
     */
    public function up(): void
    {
        Schema::table('game_stadiums', function (Blueprint $table) {
            $table->unsignedTinyInteger('base_uefa_level')->nullable()->after('base_capacity');
            $table->unsignedTinyInteger('rebuilt_uefa_level')->nullable()->after('rebuilt_capacity');
        });

        // Backfill existing game_stadiums rows from Teams.
        $teamIds = DB::table('game_stadiums')
            ->whereNull('base_uefa_level')
            ->pluck('team_id')
            ->unique()
            ->all();

        if (empty($teamIds)) {
            return;
        }

        $categoriesByTeam = DB::table('teams')
            ->whereIn('id', $teamIds)
            ->pluck('uefa_stadium_category', 'id');

        foreach ($categoriesByTeam as $teamId => $category) {
            if ($category === null) {
                continue;
            }
            DB::table('game_stadiums')
                ->where('team_id', $teamId)
                ->whereNull('base_uefa_level')
                ->update(['base_uefa_level' => (int) $category]);
        }
    }

    public function down(): void
    {
        Schema::table('game_stadiums', function (Blueprint $table) {
            $table->dropColumn(['base_uefa_level', 'rebuilt_uefa_level']);
        });
    }
};
