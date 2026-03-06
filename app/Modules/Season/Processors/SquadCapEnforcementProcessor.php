<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\ContractService;

class SquadCapEnforcementProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 109; // After ContinentalAndCupInit (106), before OnboardingReset (110)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $squadCount = ContractService::squadCount($game);

        if ($squadCount > ContractService::MAX_SQUAD_SIZE) {
            $game->addPendingAction('squad_trim', 'game.squad');
        }

        return $data;
    }
}
