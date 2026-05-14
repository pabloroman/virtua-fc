<?php

namespace App\Modules\Stadium\Services;

use App\Models\GameStadium;
use App\Models\GameStadiumProject;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;

/**
 * Resolves the *game-scoped* effective stadium capacity for a team. Wraps:
 *  - the per-game capacity overlay (game_stadiums) which holds base
 *    capacity, supplementary stands, and any rebuilt capacity;
 *  - the construction-time multiplier applied while a rebuild is actively
 *    under construction (capacity drops to a fraction of the pre-rebuild
 *    figure for one season).
 *
 * Falls back to the control-plane Team.stadium_seats if no per-game row
 * exists (long-running saves that predate the backfill migration, plus
 * non-user teams which don't get per-game rows in v1).
 *
 * Caches results per request — the resolver is bound short-lived in the
 * container, but the same fixture batch can pull the same team many times.
 */
class StadiumCapacityResolver
{
    /** @var array<string, int> game_id|team_id => effective capacity */
    private array $cache = [];

    /** @var array<string, ?GameStadium> game_id|team_id => row | null-if-missing */
    private array $stadiumCache = [];

    /** @var array<string, bool> game_id|team_id => is currently rebuilding */
    private array $rebuildingCache = [];

    public function effectiveCapacity(string $gameId, string $teamId, int $teamBaselineSeats): int
    {
        $key = $gameId.'|'.$teamId;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $stadium = $this->loadStadium($gameId, $teamId);
        $base = $stadium?->effective_capacity ?? $teamBaselineSeats;

        if ($this->isRebuilding($gameId, $teamId)) {
            $factor = (float) config('finances.stadium_costs.rebuild_construction_capacity_factor', 0.4);
            $base = (int) round($base * $factor);
        }

        return $this->cache[$key] = max(0, $base);
    }

    public function clearCache(): void
    {
        $this->cache = [];
        $this->stadiumCache = [];
        $this->rebuildingCache = [];
    }

    private function loadStadium(string $gameId, string $teamId): ?GameStadium
    {
        $key = $gameId.'|'.$teamId;
        if (array_key_exists($key, $this->stadiumCache)) {
            return $this->stadiumCache[$key];
        }

        return $this->stadiumCache[$key] = GameStadium::query()
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->first();
    }

    private function isRebuilding(string $gameId, string $teamId): bool
    {
        $key = $gameId.'|'.$teamId;
        if (array_key_exists($key, $this->rebuildingCache)) {
            return $this->rebuildingCache[$key];
        }

        return $this->rebuildingCache[$key] = GameStadiumProject::query()
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->where('type', StadiumProjectType::Rebuild->value)
            ->where('status', StadiumProjectStatus::InProgress->value)
            ->exists();
    }
}
