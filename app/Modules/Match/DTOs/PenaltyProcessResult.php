<?php

namespace App\Modules\Match\DTOs;

readonly class PenaltyProcessResult
{
    /**
     * @param  int  $homeScore  Home team penalty score
     * @param  int  $awayScore  Away team penalty score
     * @param  array  $kicks  Kick-by-kick results for frontend display
     */
    public function __construct(
        public int $homeScore,
        public int $awayScore,
        public array $kicks,
    ) {}
}
