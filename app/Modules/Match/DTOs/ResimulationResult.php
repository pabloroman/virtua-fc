<?php

namespace App\Modules\Match\DTOs;

readonly class ResimulationResult
{
    public function __construct(
        public int $newHomeScore,
        public int $newAwayScore,
        public int $oldHomeScore,
        public int $oldAwayScore,
    ) {}
}
