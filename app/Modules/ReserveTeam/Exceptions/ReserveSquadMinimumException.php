<?php

namespace App\Modules\ReserveTeam\Exceptions;

use App\Modules\Squad\Exceptions\SquadMinimumNotMetException;

/**
 * Calling up a reserve player would drop the reserve squad below its
 * composition minimum. Thrown from ReserveTeamService::callUpToFirstTeam.
 */
class ReserveSquadMinimumException extends SquadMinimumNotMetException
{
}
