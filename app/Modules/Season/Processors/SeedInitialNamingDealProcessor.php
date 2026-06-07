<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Stadium\Services\NamingRightsService;

/**
 * Materialises the pre-existing real-world naming deal a club starts the game
 * wearing (e.g. "Spotify Camp Nou") as an active naming-rights deal, so the
 * club earns the fee, the name reads as a sponsor name, and it can't be
 * re-sold until it lapses. Idempotent — only fires at first setup, when the
 * club has no naming-deal rows yet (see NamingRightsService::seedInitialDeal).
 *
 * Runs before GenerateNamingRightsOffersProcessor (105) and
 * BudgetProjectionProcessor (107) so the active deal exists when the budget
 * projection reads it and the commercial-window nudge (a no-op while a deal is
 * active) is suppressed for seeded clubs.
 */
class SeedInitialNamingDealProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly NamingRightsService $namingRightsService,
    ) {}

    public function priority(): int
    {
        return 104;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Tournament-mode games have no club economy / stadium hub.
        if ($game->isTournamentMode()) {
            return $data;
        }

        $this->namingRightsService->seedInitialDeal($game);

        return $data;
    }
}
