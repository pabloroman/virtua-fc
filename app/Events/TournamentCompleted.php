<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TournamentCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly bool $isChampion,
    ) {}
}
