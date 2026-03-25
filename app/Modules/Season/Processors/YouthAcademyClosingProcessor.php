<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Models\Game;

/**
 * Handles academy closing at season end:
 * 1. Develop loaned players (full season at 1.5x rate)
 * 2. Return loaned players to academy
 */
class YouthAcademyClosingProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
    ) {}

    public function priority(): int
    {
        return 55;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // 1. Recall all called-up players (sync abilities back before evaluation)
        $recalledPlayers = $this->youthAcademyService->recallAllCallups($game);

        if ($recalledPlayers->isNotEmpty()) {
            $data->setMetadata('academy_callups_recalled', $recalledPlayers->count());
        }

        // 2. Develop loaned players at higher rate before returning
        $this->youthAcademyService->developLoanedPlayers($game);

        // 3. Return loaned players to academy
        $returnedPlayers = $this->youthAcademyService->returnLoans($game);

        if ($returnedPlayers->isNotEmpty()) {
            $data->setMetadata('academy_loans_returned', $returnedPlayers->count());
        }

        return $data;
    }
}
