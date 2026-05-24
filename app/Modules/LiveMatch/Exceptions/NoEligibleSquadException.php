<?php

namespace App\Modules\LiveMatch\Exceptions;

use RuntimeException;

class NoEligibleSquadException extends RuntimeException
{
    public static function noActiveGame(string $iso): self
    {
        return new self("No active game found to draw players for {$iso}.");
    }

    public static function tooFewPlayers(string $iso, int $found): self
    {
        return new self("Only {$found} eligible players for {$iso} — pick a different nation (minimum is 11).");
    }
}
