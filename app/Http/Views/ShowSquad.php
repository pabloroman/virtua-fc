<?php

namespace App\Http\Views;

use App\Game\Services\ContractService;
use App\Game\Services\LineupService;
use App\Models\Game;

class ShowSquad
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get all players for the user's team, grouped by position
        $players = $this->lineupService->getPlayersByPositionGroup($gameId, $game->team_id);

        // Count expiring contracts for the badge
        $expiringContractsCount = $this->contractService->getPlayersEligibleForRenewal($game)->count();

        return view('squad', [
            'game' => $game,
            'goalkeepers' => $players['goalkeepers'],
            'defenders' => $players['defenders'],
            'midfielders' => $players['midfielders'],
            'forwards' => $players['forwards'],
            'expiringContractsCount' => $expiringContractsCount,
            'isTransferWindow' => $game->isTransferWindowOpen() || $game->isInPreseason(),
        ]);
    }
}
