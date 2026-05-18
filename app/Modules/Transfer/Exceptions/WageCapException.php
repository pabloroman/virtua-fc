<?php

namespace App\Modules\Transfer\Exceptions;

use App\Modules\Finance\DTOs\WageCapDecision;

/**
 * Thrown when a signing would push the user past the wage cap. Carries the
 * decision so the HTTP action can return a structured 422 with the shortfall
 * and the suggested players to free up.
 */
class WageCapException extends \RuntimeException
{
    public function __construct(
        public readonly WageCapDecision $decision,
        string $message,
    ) {
        parent::__construct($message);
    }
}
