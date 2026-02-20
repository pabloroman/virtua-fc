<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class SubmitPreContractOffer
{
    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        // Guard: pre-contract period
        if (!$game->isPreContractPeriod()) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.pre_contract_not_available'));
        }

        // Guard: player must have expiring contract
        $seasonEnd = $game->getSeasonEndDate();
        if (!$player->contract_until || !$player->contract_until->lte($seasonEnd)) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.player_not_expiring'));
        }

        // Guard: no existing agreed or pending pre-contract offer for this player from user
        $existingOffer = TransferOffer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereIn('status', [TransferOffer::STATUS_AGREED, TransferOffer::STATUS_PENDING])
            ->exists();

        if ($existingOffer) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('transfers.already_bidding'));
        }

        $validated = $request->validate([
            'offered_wage' => 'required|integer|min:0',
        ]);

        $offeredWageCents = (int) ($validated['offered_wage'] * 100);

        // Create the TransferOffer as pending (will be resolved after delay)
        TransferOffer::create([
            'game_id' => $gameId,
            'game_player_id' => $playerId,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => $offeredWageCents,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $game->current_date->addDays(TransferOffer::PRE_CONTRACT_OFFER_EXPIRY_DAYS),
            'game_date' => $game->current_date,
        ]);

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.pre_contract_submitted'));
    }
}
