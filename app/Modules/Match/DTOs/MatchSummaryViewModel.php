<?php

namespace App\Modules\Match\DTOs;

/**
 * Prepared view-model consumed by `resources/views/partials/match-summary.blade.php`.
 *
 * Built by `MatchSummaryPresenter::present()`.
 */
readonly class MatchSummaryViewModel
{
    /**
     * @param array<int,array{name:string,minutes:string}> $homeScorers
     * @param array<int,array{name:string,minutes:string}> $awayScorers
     */
    public function __construct(
        public int $homeTotal,
        public int $awayTotal,
        public bool $hasPenalties,
        public array $homeScorers,
        public array $awayScorers,
        public ?string $resultLabel,
        public ?string $resultColor,
        public ?string $resultBg,
    ) {}

    public function hasScorers(): bool
    {
        return count($this->homeScorers) > 0 || count($this->awayScorers) > 0;
    }
}
