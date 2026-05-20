<?php

namespace App\Modules\Competition\Promotions;

/**
 * A parent club whose relegation was cancelled to keep its reserve from
 * being pushed below the deepest tier.
 *
 * When this happens, the planner also cancels one promotion to keep the
 * tier counts balanced — the team that would have been promoted stays
 * in its tier too.
 */
final class SkippedRelegation
{
    public const REASON_RESERVE_AT_FLOOR = 'reserve_at_floor';

    public function __construct(
        public readonly string $parentTeamId,
        public readonly string $fromCompetitionId,
        public readonly string $wouldHaveLandedIn,
        public readonly string $reason,
        public readonly ?string $cancelledPromotionTeamId = null,
        public readonly ?string $cancelledPromotionFromCompetition = null,
    ) {}
}
