<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Models\Game;

/**
 * Signs free agents to AI teams before squad replenishment generates new players.
 *
 * Runs after contract expirations (4) and retirements (7), but before
 * SquadReplenishmentProcessor (9). This ensures AI teams fill roster gaps
 * from the free agent pool first, with generated players as a last resort.
 *
 * Priority: 8
 */
class AIFreeAgentSigningProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly AITransferMarketService $aiTransferMarketService,
    ) {}

    public function priority(): int
    {
        return 45;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $result = $this->aiTransferMarketService->processSeasonFreeAgentSignings($game, $data->newSeason);

        return $data->setMetadata('freeAgentSignings', $result);
    }
}
