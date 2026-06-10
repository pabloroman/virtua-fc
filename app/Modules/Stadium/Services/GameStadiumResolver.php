<?php

namespace App\Modules\Stadium\Services;

use App\Models\GameStadium;

/**
 * Resolves the *game-scoped* stadium overlay (`game_stadiums`) for a team:
 * its effective capacity (base + supplementary + any rebuild) and its
 * display name (a manual rename or an active naming-rights sponsor).
 *
 * Both reads come off the same row, so the row is loaded once per
 * (game, team) and reused — a fixture list that pulls the same home team's
 * name and capacity many times stays a single query instead of two.
 *
 * Falls back to the control-plane Team values (`stadium_seats` /
 * `stadium_name`) when no per-game row exists: long-running saves that
 * predate the backfill migration, and non-user teams which don't get a
 * per-game row in v1.
 *
 * Caches per request — bound short-lived in the container.
 */
class GameStadiumResolver
{
    /** @var array<string, ?GameStadium> "game_id|team_id" => row | null-if-missing */
    private array $stadiumCache = [];

    public function effectiveCapacity(string $gameId, string $teamId, int $teamBaselineSeats): int
    {
        $stadium = $this->loadStadium($gameId, $teamId);

        return max(0, $stadium?->effective_capacity ?? $teamBaselineSeats);
    }

    public function effectiveName(string $gameId, string $teamId, ?string $fallback): ?string
    {
        $stadium = $this->loadStadium($gameId, $teamId);

        return $stadium?->stadium_name ?? $fallback;
    }

    public function clearCache(): void
    {
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
