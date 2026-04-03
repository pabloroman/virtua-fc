<?php

namespace App\Modules\Squad\Services;

use RuntimeException;

class RegistrationException extends RuntimeException
{
    public static function duplicateNumber(): self
    {
        return new self(__('squad.number_taken'));
    }

    public static function invalidPlayers(): self
    {
        return new self(__('squad.invalid_selection'));
    }

    public static function academyAgeLimit(): self
    {
        return new self(__('squad.academy_age_limit'));
    }
}
