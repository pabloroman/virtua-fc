<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Stadium\Services\NamingRightsService;

/**
 * Generates the pre-season batch of stadium naming-rights offers (and
 * expires any deal that has run its term). Runs before
 * BudgetProjectionProcessor (107) so an expired deal is dropped from the
 * projection and an unchanged active deal is still counted.
 */
class GenerateNamingRightsOffersProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly NamingRightsService $namingRightsService,
    ) {}

    public function priority(): int
    {
        return 105;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Tournament-mode games have no club economy / stadium hub.
        if ($game->isTournamentMode()) {
            return $data;
        }

        $this->namingRightsService->generateOffers($game);

        return $data;
    }
}
