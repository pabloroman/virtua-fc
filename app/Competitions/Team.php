<?php

namespace App\Competitions;

final readonly class Team
{
    public function __construct(public string $id, public string $name, public string $image) {}
}
