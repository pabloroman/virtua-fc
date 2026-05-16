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
            ->distinct()
            ->pluck('game_id');

        if ($gameIds->isEmpty()) {
            return;
        }

        // Group game ids by mode so we issue one UPDATE per mode instead of
        // one per game. Only ~3 modes exist, so this collapses N round-trips
        // into a small constant.
        $idsByMode = [];
        foreach ($gameIds->chunk(5000) as $chunk) {
            Game::query()
                ->whereIn('id', $chunk)
                ->select(['id', 'game_mode'])
                ->toBase()
                ->orderBy('id')
                ->each(function ($row) use (&$idsByMode) {
                    $idsByMode[$row->game_mode][] = $row->id;
                });
        }

        foreach ($idsByMode as $mode => $ids) {
            foreach (array_chunk($ids, 5000) as $idChunk) {
                ManagerStats::query()
                    ->whereIn('game_id', $idChunk)
                    ->update(['game_mode' => $mode]);
            }
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
