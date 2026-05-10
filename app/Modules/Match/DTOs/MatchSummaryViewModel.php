<?php

namespace App\Modules\Match\DTOs;

use Illuminate\Support\Collection;

/**
 * Prepared view-model consumed by `resources/views/partials/match-summary.blade.php`.
 *
 * Built by `MatchSummaryPresenter::present()`. Static helpers `ratingClass()`
 * and `positionPillClass()` keep small CSS-class lookups out of the Blade
 * template without spreading them across multiple files.
 */
readonly class MatchSummaryViewModel
{
    /**
     * @param Collection<string,array<string,mixed>> $playerCards id → card array
     * @param array<int,array<string,mixed>> $pitchEntries
     * @param array<int,array<string,mixed>> $homeRoster
     * @param array<int,array<string,mixed>> $awayRoster
     * @param array<string,array<string,mixed>> $subsByOutId
     * @param array<string,int> $goalsByPlayer
     * @param array<string,int> $ownGoalsByPlayer
     * @param array<string,bool> $yellowsByPlayer
     * @param array<string,bool> $redsByPlayer
     * @param array<int,array<string,mixed>> $homeScorers
     * @param array<int,array<string,mixed>> $awayScorers
     */
    public function __construct(
        public Collection $playerCards,
        public array $pitchEntries,
        public array $homeRoster,
        public array $awayRoster,
        public array $subsByOutId,
        public array $goalsByPlayer,
        public array $ownGoalsByPlayer,
        public array $yellowsByPlayer,
        public array $redsByPlayer,
        public array $homeScorers,
        public array $awayScorers,
        public int $homeTotal,
        public int $awayTotal,
        public bool $hasPenalties,
        public ?string $resultLabel,
        public ?string $resultColor,
        public ?string $resultBg,
    ) {}

    public function hasLineup(): bool
    {
        return count($this->homeRoster) > 0 || count($this->awayRoster) > 0;
    }

    public function hasPitch(): bool
    {
        return count($this->pitchEntries) > 0;
    }

    public function hasScorers(): bool
    {
        return count($this->homeScorers) > 0 || count($this->awayScorers) > 0;
    }

    public static function ratingClass(?float $rating): string
    {
        if ($rating === null) {
            return '';
        }
        if ($rating >= 8.0) {
            return 'bg-accent-green/20 text-accent-green';
        }
        if ($rating >= 7.0) {
            return 'bg-accent-blue/20 text-accent-blue';
        }
        if ($rating >= 6.0) {
            return 'bg-surface-700 text-text-secondary';
        }
        if ($rating >= 5.0) {
            return 'bg-accent-orange/20 text-accent-orange';
        }

        return 'bg-accent-red/20 text-accent-red';
    }

    public static function positionPillClass(?string $group): string
    {
        return match ($group) {
            'GK' => 'bg-amber-600',
            'DEF' => 'bg-blue-600',
            'MID' => 'bg-green-600',
            'FWD' => 'bg-red-600',
            default => 'bg-surface-600',
        };
    }
}
