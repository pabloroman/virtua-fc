<?php

namespace App\Game\DTO;

use Carbon\Carbon;

final readonly class PlayoffRoundConfig
{
    public function __construct(
        public int $round,
        public string $name,
        public bool $twoLegged,
        public Carbon $firstLegDate,
        public ?Carbon $secondLegDate = null,
    ) {}
}
