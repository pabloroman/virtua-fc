<?php

namespace App\Game;

use Ramsey\Uuid\UuidInterface;

readonly class GameHeader
{
    public function __construct(
        public UuidInterface $gameId,
        public string $playerName,
        public Team $team,
        public ?int $nextMatchday = null,
        public string $seasonName = '2024/2025',
        public ?string $currentDate = null,
    ) {}
}
