<?php

namespace App\Modules\Match\DTOs;

readonly class ResimulationResult
{
    public function __construct(
        public int $newHomeScore,
        public int $newAwayScore,
        public int $oldHomeScore,
        public int $oldAwayScore,
        public int $homePossession = 50,
        public int $awayPossession = 50,
    ) {}
}
