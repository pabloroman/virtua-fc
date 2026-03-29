<?php

namespace App\Modules\Competition\Contracts;

use App\Models\GameStanding;
use App\Modules\Competition\DTOs\QualificationOutcome;

interface ProgressResolver
{
    /**
     * Determine the qualification outcome for a team after a league/group phase completes.
     *
     * Returns null when no notification is needed (e.g., mid-table in a league with playoffs).
     */
    public function resolve(string $gameId, string $competitionId, GameStanding $standing): ?QualificationOutcome;
}
