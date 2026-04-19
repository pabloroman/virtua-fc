<?php

namespace Tests\Unit;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Enums\PlayoffState;

/**
 * Test double for PlayoffGenerator so rule tests don't need a real generator
 * with its schedule JSON + DB lookups. Only the methods exercised by
 * ConfigDrivenPromotionRule are meaningfully implemented.
 */
class FakePlayoffGenerator implements PlayoffGenerator
{
    public function __construct(
        private PlayoffState $state = PlayoffState::NotStarted,
        private string $competitionId = 'ESP2',
        private int $totalRounds = 2,
    ) {}

    public function setState(PlayoffState $state): void { $this->state = $state; }
    public function getCompetitionId(): string { return $this->competitionId; }
    public function getQualifyingPositions(): array { return [3, 4, 5, 6]; }
    public function getDirectPromotionPositions(): array { return [1, 2]; }
    public function getTriggerMatchday(): int { return 42; }
    public function getTotalRounds(): int { return $this->totalRounds; }
    public function generateMatchups(\App\Models\Game $game, int $round): array { return []; }
    public function isComplete(\App\Models\Game $game): bool { return $this->state === PlayoffState::Completed; }
    public function state(\App\Models\Game $game): PlayoffState { return $this->state; }
    public function getRoundConfig(int $round, ?string $gameSeason = null): PlayoffRoundConfig
    {
        return new PlayoffRoundConfig(
            round: $round,
            name: "round {$round}",
            twoLegged: true,
            firstLegDate: \Carbon\Carbon::parse('2026-05-01'),
            secondLegDate: \Carbon\Carbon::parse('2026-05-08'),
        );
    }
}
