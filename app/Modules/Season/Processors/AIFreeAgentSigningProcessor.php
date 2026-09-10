<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Models\Game;

/**
 * Signs free agents to AI teams at season end.
 *
 * Runs after contract expirations and retirements have emptied roster slots,
 * and after SquadReplenishmentProcessor has generated its youth intake, so
 * it fills whatever gaps remain from the free-agent pool.
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
