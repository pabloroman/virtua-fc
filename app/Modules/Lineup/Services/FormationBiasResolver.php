<?php

namespace App\Modules\Lineup\Services;

use App\Models\ClubProfile;
use App\Models\TeamReputation;
use App\Modules\Lineup\Enums\Formation;

/**
 * Builds the per-team formation-score bias map consumed by
 * FormationRecommender::getBestFormation. Combines two signals:
 *
 *   1. ClubProfile::preferred_formation (curated real-world identity) —
 *      strong bonus on the chosen shape.
 *   2. Reputation-tier fallback pool — softer bonus on the shapes typical
 *      of the team's tier, used when no explicit preference is set.
 *
 * The bias is additive on top of the recommender's mechanical score, so
 * a team whose squad genuinely cannot support its preferred shape still
 * gets the organic best-fit recommendation.
 */
class FormationBiasResolver
{
    /** Score bonus applied to a club's curated preferred formation. */
    private const PRIMARY_BONUS = 12;

    /** Score bonus applied to the top tier-fallback pick (smaller bonus to runners-up). */
    private const TIER_TOP_BONUS = 6;
    private const TIER_SECONDARY_BONUS = 3;

    /**
     * Reputation-tier formation pools. Teams without a curated
     * preferred_formation get a softer bonus on shapes typical of their
     * tier so the recommendation still has flavor instead of always
     * collapsing to 4-3-3.
     *
     * Order matters: index 0 gets TIER_TOP_BONUS, index 1+ get
     * TIER_SECONDARY_BONUS.
     *
     * @var array<string, list<string>>
     */
    private const TIER_POOLS = [
        // Elite/continental sides skew toward attacking shapes that lean
        // on technically superior wide forwards and creative #10s.
        ClubProfile::REPUTATION_ELITE => ['4-3-3', '4-2-3-1', '3-4-3'],
        ClubProfile::REPUTATION_CONTINENTAL => ['4-2-3-1', '4-3-3', '4-4-2'],

        // Mid-tier sides typically run a balanced two-banks-of-four or a
        // double pivot with an attacking #10.
        ClubProfile::REPUTATION_ESTABLISHED => ['4-2-3-1', '4-4-2', '4-1-4-1'],

        // Modest/local sides skew compact and reactive — sit deep, hit
        // on the break, sometimes drop a third centre-back for safety.
        ClubProfile::REPUTATION_MODEST => ['4-4-2', '4-1-4-1', '4-2-3-1'],
        ClubProfile::REPUTATION_LOCAL => ['4-4-2', '5-3-2', '5-4-1'],
    ];

    /**
     * Resolve the bias map for a team.
     *
     * @return array<string, int> formation_value => bonus_points
     */
    public function resolveForTeam(string $gameId, string $teamId): array
    {
        $preferred = ClubProfile::where('team_id', $teamId)->value('preferred_formation');

        if ($preferred && Formation::tryFrom($preferred)) {
            return [$preferred => self::PRIMARY_BONUS];
        }

        $reputation = TeamReputation::resolveLevel($gameId, $teamId);

        return $this->biasForTier($reputation);
    }

    /**
     * Resolve the bias map from a reputation tier alone (no team context).
     * Used as the fallback path and for unit testing.
     *
     * @return array<string, int>
     */
    public function biasForTier(string $reputationLevel): array
    {
        $pool = self::TIER_POOLS[$reputationLevel] ?? self::TIER_POOLS[ClubProfile::REPUTATION_LOCAL];

        $bias = [];
        foreach ($pool as $i => $formationValue) {
            $bias[$formationValue] = $i === 0 ? self::TIER_TOP_BONUS : self::TIER_SECONDARY_BONUS;
        }

        return $bias;
    }
}
