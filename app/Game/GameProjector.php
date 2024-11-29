<?php

namespace App\Game;

use App\Game\Events\GameCreated;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class GameProjector extends Projector
{
    public function onGameCreated(GameCreated $event)
    {
        \App\Models\Game::create([
            'uuid' => $event->aggregateRootUuid(),
            'user_id' => $event->userId,
            'player_name' => $event->playerName,
            'team_id' => $event->teamId,
        ]);
    }
}
