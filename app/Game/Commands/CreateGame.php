<?php

namespace App\Game\Commands;

final readonly class CreateGame
{
    public function __construct(
        public string $userId,
        public string $playerName,
        public string $teamId,
        public string $gameMode = 'career',
    ) {}
}
