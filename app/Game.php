<?php

namespace App;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class Game
{
    private function __construct(public UuidInterface $id)
    {}

    public static function create(CreateGame $command): Game
    {
        $id = Uuid::uuid4();
        // Create event

        return new self($id);
    }
}
