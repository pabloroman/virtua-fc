<?php

namespace App\Modules\Match\DTOs;

use Illuminate\Support\Collection;

readonly class ExtraTimeProcessResult
{
    /**
     * @param  int  $homeScoreET  Goals scored by home team in extra time
     * @param  int  $awayScoreET  Goals scored by away team in extra time
     * @param  Collection  $storedEvents  Persisted MatchEvent models with relations loaded
     * @param  bool  $needsPenalties  Whether the tie remains level after extra time
     */
    public function __construct(
        public int $homeScoreET,
        public int $awayScoreET,
        public Collection $storedEvents,
        public bool $needsPenalties,
    ) {}
}
