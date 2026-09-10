<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;

/**
 * Applies pending contract renewals at end of season.
 * Players who renewed their contracts get their new wages applied.
 * Runs after the pre-contract completions and before player development.
 */
class ContractRenewalProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function priority(): int
    {
        return 35;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $renewedPlayers = $this->contractService->applyPendingWages($game);

        // Store renewed contracts info in metadata
        $renewalsData = $renewedPlayers->map(fn ($player) => [
            'playerId' => $player->id,
            'playerName' => $player->name,
            'newWage' => $player->annual_wage,
            'formattedWage' => $player->formatted_wage,
        ])->toArray();

        return $data->setMetadata('contractRenewals', $renewalsData);
    }
}
