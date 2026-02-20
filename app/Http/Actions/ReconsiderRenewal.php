<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;

class ReconsiderRenewal
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with('latestRenewalNegotiation')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        if (!$player->hasDeclinedRenewal()) {
            return redirect()->route('game.transfers.outgoing', $gameId);
        }

        $this->contractService->reconsiderRenewal($player);

        return redirect()->route('game.transfers.outgoing', $gameId)
            ->with('success', __('messages.renewal_reconsidered', ['player' => $player->name]));
    }
}
