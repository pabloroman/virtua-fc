<?php

namespace App\Game\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

final class GameCreated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $teamId,
        public string $playerName,
        public string $gameMode = 'career',
    )
    {}
}
