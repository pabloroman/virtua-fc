<?php

namespace App\Modules\Competition\DTOs;

/**
 * Result of walking a competition's standings (or simulated season) once and
 * assigning teams to direct-promotion and playoff-bracket slots.
 *
 * Both lists are guaranteed disjoint by construction (single sequential walk
 * with reserve filtering) — the allocator hands the first $directCount eligible
 * teams to direct promotion, then the next $playoffCount to the bracket. This
 * is the invariant PromotionRelegationProcessor::validatePlan asserts.
 *
 * Each entry carries the team's standings position so callers can reason about
 * seeding (bracket order) or display (notification text).
 */
final class PromotionSlotAllocation
{
    /**
     * @param  array<int, array{teamId: string, position: int, teamName: string}>  $directPromotions
     *   Ordered by standings position ascending — first entry is the highest finisher
     *   among eligible teams.
     * @param  array<int, array{teamId: string, position: int, teamName: string}>  $playoffQualifiers
     *   Ordered by standings position ascending — bracket seeding follows this order
     *   (index 0 is the highest seed).
     */
    public function __construct(
        public readonly array $directPromotions,
        public readonly array $playoffQualifiers,
    ) {}

    public function isEmpty(): bool
    {
        return $this->directPromotions === [] && $this->playoffQualifiers === [];
    }
}
