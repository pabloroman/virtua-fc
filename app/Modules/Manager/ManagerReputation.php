<?php

namespace App\Modules\Manager;

use App\Models\ClubProfile;

/**
 * Tier mapping for the per-game pro-manager reputation stat. Mirrors
 * TeamReputation::TIER_THRESHOLDS so the same 5-tier ladder
 * (local..elite) governs both club and manager standing — that
 * symmetry is what lets JobOfferService treat manager reputation as
 * a parallel anchor to club reputation when picking offer targets.
 */
final class ManagerReputation
{
    /**
     * Points thresholds for each tier. A manager is at a given tier
     * when their points are >= that tier's threshold and < the next.
     * Identical scale to TeamReputation so a Tier-N manager naturally
     * fields offers from Tier-N clubs.
     */
    public const TIER_THRESHOLDS = [
        ClubProfile::REPUTATION_LOCAL        => 0,
        ClubProfile::REPUTATION_MODEST       => 100,
        ClubProfile::REPUTATION_ESTABLISHED  => 200,
        ClubProfile::REPUTATION_CONTINENTAL  => 300,
        ClubProfile::REPUTATION_ELITE        => 400,
    ];

    /** Reputation can never drop below this floor. */
    public const MIN_POINTS = 0;

    /**
     * Hard ceiling on how many points a single season can add.
     * Forces continental/elite offers to require ~4+ strong seasons
     * even on a perfect career (one tier jump per season max).
     */
    public const MAX_SEASONAL_GAIN = 60;

    /**
     * Floor on how many points a single season can subtract.
     * Bad seasons hurt, but one disaster shouldn't undo a Klopp-era
     * career — symmetric with MAX_SEASONAL_GAIN halved.
     */
    public const MAX_SEASONAL_LOSS = 30;

    /**
     * Convert points to a reputation tier label (local..elite).
     */
    public static function levelFromPoints(int $points): string
    {
        $level = ClubProfile::REPUTATION_LOCAL;

        foreach (self::TIER_THRESHOLDS as $tier => $threshold) {
            if ($points >= $threshold) {
                $level = $tier;
            }
        }

        return $level;
    }

    /**
     * Map a manager-reputation tier onto the (league_tier, club_reputation)
     * pair whose prestige rank should act as the offer-pool floor for a
     * manager at that tier. Tuned to a gradual curve across the ladder so
     * a fresh local-tier manager doesn't immediately field top-flight
     * offers — manager rep needs to grow into the upper ranks just like
     * club rep does.
     *
     *   local         → T3/local        (rank  0) — no floor; club anchor wins
     *   modest        → T2/local        (rank  5) — promoted-club ceiling
     *   established   → T2/established  (rank  7) — Segunda mainstay
     *   continental   → T1/local        (rank 10) — La Liga newcomer floor
     *   elite         → T1/continental  (rank 13) — Champions League pedigree
     *
     * @return array{0: int, 1: string}  [leagueTier, reputationLevel]
     */
    public static function anchorFor(string $level): array
    {
        return match ($level) {
            ClubProfile::REPUTATION_LOCAL        => [3, ClubProfile::REPUTATION_LOCAL],
            ClubProfile::REPUTATION_MODEST       => [2, ClubProfile::REPUTATION_LOCAL],
            ClubProfile::REPUTATION_ESTABLISHED  => [2, ClubProfile::REPUTATION_ESTABLISHED],
            ClubProfile::REPUTATION_CONTINENTAL  => [1, ClubProfile::REPUTATION_LOCAL],
            ClubProfile::REPUTATION_ELITE        => [1, ClubProfile::REPUTATION_CONTINENTAL],
            default                               => [3, ClubProfile::REPUTATION_LOCAL],
        };
    }
}
