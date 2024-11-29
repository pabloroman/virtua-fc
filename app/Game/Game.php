<?php

namespace App\Game;

use App\Game\Events\GameCreated;
use Ramsey\Uuid\Uuid;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

final class Game extends AggregateRoot
{
    public static function create(CreateGame $command): Game
    {
        $id = Uuid::uuid4();
        $game = self::retrieve($id);

        $game->recordThat(new GameCreated($command->userId, $command->teamId, $command->playerName));
        $game->persist();

        return $game;
    }
}
