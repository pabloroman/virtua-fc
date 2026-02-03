<?php

namespace App\Http\Actions;

use App\Game\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;

class OfferRenewal
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        if (!$player->canBeOfferedRenewal()) {
            return redirect()->route('game.squad.contracts', $gameId)
                ->with('error', 'This player cannot be offered a renewal.');
        }

        // Get what the player demands
        $demand = $this->contractService->calculateRenewalDemand($player);

        // Process the renewal (player accepts automatically for now)
        $success = $this->contractService->processRenewal(
            $player,
            $demand['wage'],
            $demand['contractYears']
        );

        if ($success) {
            return redirect()->route('game.squad.contracts', $gameId)
                ->with('success', "{$player->name} has agreed to a {$demand['contractYears']}-year extension at {$demand['formattedWage']}/year (effective next season).");
        }

        return redirect()->route('game.squad.contracts', $gameId)
            ->with('error', 'Failed to process the renewal.');
    }
}
