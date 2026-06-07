<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Stadium\Services\NamingRightsService;

/**
 * Rolls stadium naming-rights deals over into the new season: expires any
 * deal that has run its term (restoring the pre-deal stadium name) and clears
 * stale unaccepted offers. Runs before BudgetProjectionProcessor (107) so an
 * expired deal is dropped from the projection and an unchanged active deal is
 * still counted.
 *
 * New offers are NOT minted here, nor do they arrive on their own — the
 * manager seeks them from the Commercial page. This processor only drops a
 * once-per-pre-season nudge that the commercial window is open, so the
 * proactive lever is discoverable.
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

        $this->namingRightsService->rolloverForNewSeason($game);
        $this->namingRightsService->notifyCommercialWindowOpen($game);

        return $data;
    }
}
