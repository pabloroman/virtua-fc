<?php

namespace App\Modules\Match\DTOs;

readonly class MatchdayAdvanceResult
{
    private function __construct(
        public string $type,
        public ?string $matchId = null,
        public ?string $redirectUrl = null,
        public ?array $pendingAction = null,
    ) {}

    public static function blocked(?array $pendingAction): self
    {
        return new self(type: 'blocked', pendingAction: $pendingAction);
    }

    public static function seasonComplete(): self
    {
        return new self(type: 'season_complete');
    }

    public static function liveMatch(string $matchId): self
    {
        return new self(type: 'live_match', matchId: $matchId);
    }

    public static function results(string $redirectUrl): self
    {
        return new self(type: 'results', redirectUrl: $redirectUrl);
    }

    public static function done(): self
    {
        return new self(type: 'done');
    }
}
