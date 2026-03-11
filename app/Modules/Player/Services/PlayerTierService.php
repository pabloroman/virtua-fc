<?php

namespace App\Modules\Player\Services;

use Illuminate\Support\Facades\DB;

class PlayerTierService
{
    // Market value thresholds in cents (lower bound of each tier)
    public const TIER_5_MIN = 50_000_000_00;  // €50M+  World Class
    public const TIER_4_MIN = 20_000_000_00;  // €20M+  Excellent
    public const TIER_3_MIN =  5_000_000_00;  // €5M+   Good
    public const TIER_2_MIN =  1_000_000_00;  // €1M+   Average
    // Tier 1: below €1M                        Developing

    /**
     * Compute tier from market value in cents (pure function, no DB).
     */
    public static function tierFromMarketValue(int $marketValueCents): int
    {
        return match (true) {
            $marketValueCents >= self::TIER_5_MIN => 5,
            $marketValueCents >= self::TIER_4_MIN => 4,
            $marketValueCents >= self::TIER_3_MIN => 3,
            $marketValueCents >= self::TIER_2_MIN => 2,
            default => 1,
        };
    }

    /**
     * Batch-recompute tiers for specific player IDs.
     *
     * @param array<string> $playerIds UUIDs of game_players to update
     */
    public function recomputeTiers(array $playerIds): void
    {
        if (empty($playerIds)) {
            return;
        }

        $idList = "'" . implode("','", $playerIds) . "'";

        DB::statement("
            UPDATE game_players SET tier = CASE
                WHEN market_value_cents >= " . self::TIER_5_MIN . " THEN 5
                WHEN market_value_cents >= " . self::TIER_4_MIN . " THEN 4
                WHEN market_value_cents >= " . self::TIER_3_MIN . " THEN 3
                WHEN market_value_cents >= " . self::TIER_2_MIN . " THEN 2
                ELSE 1
            END
            WHERE id IN ({$idList})
        ");
    }

    /**
     * Recompute tiers for ALL players in a game.
     */
    public function recomputeAllTiersForGame(string $gameId): void
    {
        DB::statement("
            UPDATE game_players SET tier = CASE
                WHEN market_value_cents >= " . self::TIER_5_MIN . " THEN 5
                WHEN market_value_cents >= " . self::TIER_4_MIN . " THEN 4
                WHEN market_value_cents >= " . self::TIER_3_MIN . " THEN 3
                WHEN market_value_cents >= " . self::TIER_2_MIN . " THEN 2
                ELSE 1
            END
            WHERE game_id = ?
        ", [$gameId]);
    }
}
