<?php

namespace App\Game\Commands;

final readonly class AdvanceMatchday
{
    /**
     * @param array<array{matchId: string, homeTeamId: string, awayTeamId: string, homeScore: int, awayScore: int, competitionId: string, events: array}> $matchResults
     */
    public function __construct(
        public int $matchday,
        public string $currentDate,
        public array $matchResults,
    ) {}
}
