<?php

namespace App\Modules\Competition\ProgressResolvers;

use App\Models\GameStanding;
use App\Modules\Competition\Contracts\ProgressResolver;
use App\Modules\Competition\DTOs\QualificationOutcome;

class SwissProgressResolver implements ProgressResolver
{
    private const DIRECT_KNOCKOUT_MAX_POSITION = 8;
    private const PLAYOFF_MAX_POSITION = 24;

    public function resolve(string $gameId, string $competitionId, GameStanding $standing): ?QualificationOutcome
    {
        if ($standing->position <= self::DIRECT_KNOCKOUT_MAX_POSITION) {
            return QualificationOutcome::advanced('cup.swiss_direct_r16');
        }

        if ($standing->position <= self::PLAYOFF_MAX_POSITION) {
            return QualificationOutcome::playoff('cup.swiss_knockout_playoff');
        }

        return QualificationOutcome::eliminated('cup.swiss_eliminated');
    }
}
