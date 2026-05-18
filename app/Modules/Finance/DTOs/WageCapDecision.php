<?php

namespace App\Modules\Finance\DTOs;

/**
 * Outcome of a WageBudgetService::canAfford() check.
 *
 * `allowed` is false only when at least one applicable gate is hard-rejecting
 * (current or next-season for new signings). A renewal always returns
 * `allowed = true` but may set `shortfallCents > 0` to drive a soft warning.
 */
final readonly class WageCapDecision
{
    public function __construct(
        public bool $allowed,
        public int $shortfallCents,
        public ?WageHeadroom $currentSeason,
        public ?WageHeadroom $nextSeason,
        public string $blockedBy = '',
    ) {}

    public static function allow(?WageHeadroom $current, ?WageHeadroom $next, int $softShortfall = 0): self
    {
        return new self(true, $softShortfall, $current, $next, '');
    }

    public static function reject(int $shortfall, ?WageHeadroom $current, ?WageHeadroom $next, string $blockedBy): self
    {
        return new self(false, $shortfall, $current, $next, $blockedBy);
    }
}
