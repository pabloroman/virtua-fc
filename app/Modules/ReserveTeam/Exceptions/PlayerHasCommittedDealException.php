<?php

namespace App\Modules\ReserveTeam\Exceptions;

/**
 * A first-team ↔ reserve move was attempted for a player who already has a
 * committed deal (GamePlayer::hasCommittedDeal()). The deal completes from
 * wherever the player was when it was agreed, so he stays put until then.
 */
class PlayerHasCommittedDealException extends \DomainException
{
    public static function forPlayer(string $playerName): self
    {
        return new self("{$playerName} has a committed transfer or pre-contract and cannot be moved between squads.");
    }
}
