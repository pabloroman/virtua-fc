<?php

namespace App\Modules\Competition\Promotions;

/**
 * One team relocation in a PromotionRelegationPlan.
 *
 * `reason` describes which planner rule produced the move and is recorded so
 * post-hoc inspection (logs, diagnostic command output) can explain why each
 * team moved. The executor doesn't branch on it.
 */
final class PromotionMove
{
    public const REASON_PROMOTION = 'promotion';
    public const REASON_PROMOTION_PLAYOFF = 'promotion_playoff';
    public const REASON_RELEGATION = 'relegation';
    public const REASON_RESERVE_CASCADE = 'reserve_cascade';
    public const REASON_CASCADE_COMPENSATION = 'cascade_compensation';

    public function __construct(
        public readonly string $teamId,
        public readonly string $fromCompetitionId,
        public readonly string $toCompetitionId,
        public readonly string $reason,
        public readonly string $teamName = '',
    ) {}

    public function isPromotion(): bool
    {
        return in_array($this->reason, [
            self::REASON_PROMOTION,
            self::REASON_PROMOTION_PLAYOFF,
            self::REASON_CASCADE_COMPENSATION,
        ], true);
    }

    public function isRelegation(): bool
    {
        return $this->reason === self::REASON_RELEGATION
            || $this->reason === self::REASON_RESERVE_CASCADE;
    }
}
