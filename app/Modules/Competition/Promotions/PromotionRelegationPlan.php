<?php

namespace App\Modules\Competition\Promotions;

/**
 * Country-scoped, fully resolved plan for an end-of-season
 * promotion/relegation pass.
 *
 * Produced by CountryPromotionRelegationPlanner from a pre-swap snapshot;
 * consumed by PromotionRelegationExecutor which applies it to the DB inside
 * a single transaction. Plans are immutable.
 *
 * Tier-count invariants must hold after applying every move:
 *   - Each competition's team count is unchanged
 *   - No team appears in two competitions
 *   - No reserve shares a competition with its parent
 *
 * These are asserted by the planner before returning; the executor trusts
 * the plan it receives.
 */
final class PromotionRelegationPlan
{
    /**
     * @param  list<PromotionMove>  $moves
     * @param  list<SkippedRelegation>  $skippedRelegations
     * @param  list<string>  $touchedCompetitionIds  Competitions where at least
     *     one entry changed; used by the executor to scope position resort and
     *     non-played-league re-simulation.
     */
    public function __construct(
        public readonly string $countryCode,
        public readonly array $moves,
        public readonly array $skippedRelegations = [],
        public readonly array $touchedCompetitionIds = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->moves === [];
    }

    /**
     * @return list<PromotionMove>
     */
    public function movesByReason(string $reason): array
    {
        return array_values(array_filter(
            $this->moves,
            fn (PromotionMove $m) => $m->reason === $reason,
        ));
    }

    /**
     * @return list<PromotionMove>
     */
    public function promotionsInto(string $competitionId): array
    {
        return array_values(array_filter(
            $this->moves,
            fn (PromotionMove $m) => $m->toCompetitionId === $competitionId && $m->isPromotion(),
        ));
    }

    /**
     * @return list<PromotionMove>
     */
    public function relegationsInto(string $competitionId): array
    {
        return array_values(array_filter(
            $this->moves,
            fn (PromotionMove $m) => $m->toCompetitionId === $competitionId && $m->isRelegation(),
        ));
    }
}
