<?php

namespace App\Game\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

final class MatchResultRecorded extends ShouldBeStored
{
    public function __construct(
        public string $matchId,
        public string $homeTeamId,
        public string $awayTeamId,
        public int $homeScore,
        public int $awayScore,
        public string $competitionId,
        public int $matchday,
        public array $events = [], // Match events (goals, cards, etc.)
    ) {}
}
