<?php

namespace App\Modules\Squad\DTOs;

class SuspensionRuleSet
{
    /**
     * @param  array<int, int>  $yellowCardThresholds  Mode 1 — exact thresholds: [yellowCount => banMatches]
     * @param  int|null  $yellowCardSuspendAt  Mode 2 — first suspension at this many yellows
     * @param  int  $yellowCardRepeatEvery  Mode 2 — suspend again every N yellows after the first
     * @param  int|null  $yellowCardResetAfterRound  Reset yellow cards after this knockout round (null = no reset)
     */
    public function __construct(
        public readonly array $yellowCardThresholds = [],
        public readonly ?int $yellowCardSuspendAt = null,
        public readonly int $yellowCardRepeatEvery = 2,
        public readonly ?int $yellowCardResetAfterRound = null,
    ) {}

    /**
     * Check if a player's yellow card count triggers a suspension.
     *
     * @return int|null Number of match ban, or null if no suspension
     */
    public function checkAccumulation(int $yellowCards): ?int
    {
        // Mode 2 — interval-based (FIFA WC, UCL/UEL)
        if ($this->yellowCardSuspendAt !== null) {
            if ($yellowCards >= $this->yellowCardSuspendAt
                && ($yellowCards - $this->yellowCardSuspendAt) % $this->yellowCardRepeatEvery === 0) {
                return 1;
            }

            return null;
        }

        // Mode 1 — exact thresholds (La Liga)
        return $this->yellowCardThresholds[$yellowCards] ?? null;
    }

    /**
     * Check if one more yellow card would trigger a suspension.
     */
    public function isAtRisk(int $yellowCards): bool
    {
        return $this->checkAccumulation($yellowCards + 1) !== null;
    }

    /**
     * La Liga rules: suspend at 5 (1 match), 10 (2 matches), 15 (3 matches). No reset.
     */
    public static function default(): self
    {
        return new self(
            yellowCardThresholds: [5 => 1, 10 => 2, 15 => 3],
        );
    }

    /**
     * FIFA World Cup: suspend every 2 yellows. Reset after quarter-finals.
     */
    public static function worldCup(): self
    {
        return new self(
            yellowCardSuspendAt: 2,
            yellowCardRepeatEvery: 2,
            yellowCardResetAfterRound: \App\Modules\Competition\Services\WorldCupKnockoutGenerator::ROUND_QUARTER_FINALS,
        );
    }

    /**
     * Copa del Rey: suspend every 3 yellows. Reset after round 3 (Round of 16).
     */
    public static function copaDelRey(): self
    {
        return new self(
            yellowCardSuspendAt: 3,
            yellowCardRepeatEvery: 3,
            yellowCardResetAfterRound: 3,
        );
    }

    /**
     * UEFA club competitions (UCL/UEL): first suspend at 3, then every 2. Reset after quarter-finals.
     */
    public static function uefaClub(): self
    {
        return new self(
            yellowCardSuspendAt: 3,
            yellowCardRepeatEvery: 2,
            yellowCardResetAfterRound: 3, // Quarter-finals in UCL bracket
        );
    }
}
