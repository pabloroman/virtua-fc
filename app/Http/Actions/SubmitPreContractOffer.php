<?php

namespace App\Http\Actions;

use App\Game\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class SubmitPreContractOffer
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        // Guard: pre-contract period
        if (!$game->isPreContractPeriod()) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('messages.pre_contract_not_available'));
        }

        // Guard: player must have expiring contract
        $seasonEnd = $game->getSeasonEndDate();
        if (!$player->contract_until || !$player->contract_until->lte($seasonEnd)) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('messages.player_not_expiring'));
        }

        // Guard: no existing agreed pre-contract offer for this player from user
        $existingAgreed = TransferOffer::where('game_id', $gameId)
            ->where('game_player_id', $playerId)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->exists();

        if ($existingAgreed) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('transfers.already_bidding'));
        }

        $validated = $request->validate([
            'offered_wage' => 'required|integer|min:0',
        ]);

        $offeredWageCents = (int) ($validated['offered_wage'] * 100);

        // Evaluate the offer
        $evaluation = $this->scoutingService->evaluatePreContractOffer($player, $offeredWageCents);

        // Create the TransferOffer record
        TransferOffer::create([
            'game_id' => $gameId,
            'game_player_id' => $playerId,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => $offeredWageCents,
            'status' => $evaluation['accepted'] ? TransferOffer::STATUS_AGREED : TransferOffer::STATUS_REJECTED,
            'expires_at' => $game->current_date->addDays(30),
            'game_date' => $game->current_date,
            'resolved_at' => $game->current_date,
        ]);

        if ($evaluation['accepted']) {
            return redirect()->route('game.scouting', $gameId)
                ->with('success', $evaluation['message']);
        }

        return redirect()->route('game.scouting', $gameId)
            ->with('error', $evaluation['message']);
    }
}
