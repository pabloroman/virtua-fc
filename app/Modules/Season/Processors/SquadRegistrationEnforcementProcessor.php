<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Squad\Services\SquadNumberService;

class SquadRegistrationEnforcementProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly SquadNumberService $squadNumberService,
    ) {}

    public function priority(): int
    {
        return 109;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Enable squad registration for existing games starting a new season.
        // New games already have this set to true at creation.
        if (! $game->squad_registration_enabled) {
            $game->update(['squad_registration_enabled' => true]);
        }

        // Auto-assign numbers after season transition (loan returns, contract expirations, etc.)
        $unresolvable = $this->squadNumberService->reassignNumbers($game);

        if ($unresolvable > 0) {
            $game->addPendingAction('squad_registration', 'game.squad.registration');
        }

        return $data;
    }
}
