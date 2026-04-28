<?php

namespace App\Modules\ReserveTeam\Exceptions;

use App\Modules\Squad\Exceptions\SquadMinimumNotMetException;

/**
 * Sending a called-up player back to the reserve would drop the first
 * team below its composition minimum. Thrown from
 * ReserveTeamService::sendBackToReserve.
 */
class FirstTeamSquadMinimumException extends SquadMinimumNotMetException
{
}
