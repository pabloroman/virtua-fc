<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\ContractService;
use App\Models\Game;

/**
 * Applies pending contract renewals at end of season.
 * Players who renewed their contracts get their new wages applied.
 * Priority: 6 (runs early, after pre-contract transfers but before development)
 */
class ContractRenewalProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function priority(): int
    {
        return 6;
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
