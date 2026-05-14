<?php

namespace App\Modules\Stadium\Services;

use App\Models\GameStadium;

/**
 * Resolves the *game-scoped* effective stadium capacity for a team. Wraps
 * the per-game capacity overlay (game_stadiums), which holds base
 * capacity, supplementary stands, and any rebuilt capacity.
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

    public function effectiveCapacity(string $gameId, string $teamId, int $teamBaselineSeats): int
    {
        $key = $gameId.'|'.$teamId;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $stadium = $this->loadStadium($gameId, $teamId);

        return $this->cache[$key] = max(0, $stadium?->effective_capacity ?? $teamBaselineSeats);
    }

    public function clearCache(): void
    {
        $this->cache = [];
        $this->stadiumCache = [];
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
}
