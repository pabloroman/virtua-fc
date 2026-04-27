<?php

namespace App\Modules\Transfer\Exceptions;

use App\Modules\Squad\Exceptions\SquadMinimumNotMetException;

/**
 * A transfer/loan flow (listing for sale, listing for loan-out, or
 * accepting an incoming offer) would drop the player's current squad
 * below its composition minimum.
 */
class SquadMinimumException extends SquadMinimumNotMetException
{
}
