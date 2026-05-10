<?php

namespace App\Modules\Match\DTOs;

/**
 * Prepared view-model consumed by `resources/views/partials/match-summary.blade.php`.
 *
 * Built by `MatchSummaryPresenter::present()`.
 *
 * The `mvp` and `lineups` fields are only populated in `mode = 'full'` so
 * the compact callers (results page) don't pay the lineup lookup cost.
 */
readonly class MatchSummaryViewModel
{
    /**
     * @param array<int,array{name:string,minutes:string}> $homeScorers
     * @param array<int,array{name:string,minutes:string}> $awayScorers
     * @param array{name:string, side:string}|null $mvp
     */
    public function __construct(
        public int $homeTotal,
        public int $awayTotal,
        public bool $hasPenalties,
        public array $homeScorers,
        public array $awayScorers,
        public ?array $mvp = null,
        public ?MatchLineupsViewModel $lineups = null,
    ) {}

    public function hasScorers(): bool
    {
        return count($this->homeScorers) > 0 || count($this->awayScorers) > 0;
    }
}
