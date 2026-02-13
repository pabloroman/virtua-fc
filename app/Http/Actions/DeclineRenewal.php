<?php

namespace App\Http\Actions;

use App\Game\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;

class DeclineRenewal
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

        if (!$player->isContractExpiring() || $player->isRetiring() || $player->hasRenewalAgreed()) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.cannot_renew'));
        }

        // Cancel any active negotiation
        $activeNegotiation = RenewalNegotiation::where('game_player_id', $player->id)
            ->whereIn('status', [RenewalNegotiation::STATUS_OFFER_PENDING, RenewalNegotiation::STATUS_PLAYER_COUNTERED])
            ->first();

        if ($activeNegotiation) {
            $this->contractService->cancelNegotiation($activeNegotiation);
        } else {
            $this->contractService->declineWithoutNegotiation($player);
        }

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.renewal_declined', ['player' => $player->name]));
    }
}
