<?php

namespace App\Game\Commands;

class ConductCupDraw
{
    public function __construct(
        public readonly string $competitionId,
        public readonly int $roundNumber,
    ) {}
}
