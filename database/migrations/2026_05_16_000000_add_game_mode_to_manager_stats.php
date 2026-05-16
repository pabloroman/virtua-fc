<?php

use App\Models\Game;
use App\Models\ManagerStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->string('game_mode', 16)->nullable()->after('team_id');
            $table->index('game_mode');
        });

        // Backfill from games. manager_stats lives on the control plane and games
        // on the tenant plane — issue two separate queries so we never JOIN
        // across planes (see CLAUDE.md → "Control plane / tenant plane").
        $gameIds = ManagerStats::query()
            ->whereNotNull('game_id')
            ->pluck('game_id')
            ->unique()
            ->values();

        if ($gameIds->isEmpty()) {
            return;
        }

        $modesByGame = Game::query()
            ->whereIn('id', $gameIds)
            ->pluck('game_mode', 'id');

        foreach ($modesByGame as $gameId => $mode) {
            ManagerStats::query()
                ->where('game_id', $gameId)
                ->update(['game_mode' => $mode]);
        }
    }

    public function down(): void
    {
        Schema::table('manager_stats', function (Blueprint $table) {
            $table->dropIndex(['game_mode']);
            $table->dropColumn('game_mode');
        });
    }
};
