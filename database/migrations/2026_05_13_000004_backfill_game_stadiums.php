<?php

use App\Models\Game;
use App\Models\GameStadium;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // One-shot backfill so existing games gain a stadium row without
        // needing a re-seed. Resolves the team via a separate query rather
        // than a JOIN to stay single-plane.
        Game::query()->select(['id', 'team_id'])->chunkById(200, function ($games) {
            $teamIds = $games->pluck('team_id')->unique()->all();
            $seatsByTeam = Team::query()
                ->whereIn('id', $teamIds)
                ->pluck('stadium_seats', 'id');

            $rows = [];
            foreach ($games as $game) {
                if (! isset($seatsByTeam[$game->team_id])) {
                    continue;
                }
                $rows[] = [
                    'game_id' => $game->id,
                    'team_id' => $game->team_id,
                    'base_capacity' => (int) $seatsByTeam[$game->team_id],
                    'supplementary_seats' => 0,
                    'rebuilt_capacity' => null,
                ];
            }

            if (! empty($rows)) {
                // insertOrIgnore: a few games may already have a row if the
                // migration runs after some new games were created via the
                // updated GameCreationService.
                GameStadium::query()->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        // No-op: rows are tied to game lifecycle via FK cascade.
    }
};
