<?php

namespace App\Modules\LiveMatch\Enums;

enum LiveMatchPhase: string
{
    case Lobby = 'lobby';
    case Live = 'live';
    case Paused = 'paused';
    case Finished = 'finished';
    case Abandoned = 'abandoned';

    public function isTerminal(): bool
    {
        return $this === self::Finished || $this === self::Abandoned;
    }

    public function isInPlay(): bool
    {
        return $this === self::Live || $this === self::Paused;
    }
}
