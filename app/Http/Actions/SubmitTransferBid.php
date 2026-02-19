<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class SubmitTransferBid
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        $validated = $request->validate([
            'bid_amount' => 'required|numeric|min:0',
        ]);

        $bidAmountCents = (int) ($validated['bid_amount'] * 100);

        // Budget validation
        $investment = $game->currentInvestment;
        if ($investment && $bidAmountCents > $investment->transfer_budget) {
            return redirect()->route('game.scouting', $gameId)
                ->with('error', __('messages.bid_exceeds_budget'));
        }

        // Evaluate bid
        $evaluation = $this->scoutingService->evaluateBid($player, $bidAmountCents);
        $wageDemand = $this->scoutingService->calculateWageDemand($player);

        if ($evaluation['result'] === 'accepted') {
            // Create offer
            $offer = TransferOffer::create([
                'game_id' => $gameId,
                'game_player_id' => $playerId,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => $bidAmountCents,
                'asking_price' => $evaluation['asking_price'],
                'offered_wage' => $wageDemand,
                'status' => TransferOffer::STATUS_PENDING, // Will be updated by acceptIncomingOffer
                'expires_at' => $game->current_date->addDays(30),
                'game_date' => $game->current_date,
            ]);

            // Complete immediately if window open, otherwise mark as agreed
            $completedImmediately = $this->transferService->acceptIncomingOffer($offer);

            if ($completedImmediately) {
                return redirect()->route('game.scouting', $gameId)
                    ->with('success', __('messages.transfer_complete', ['player' => $player->player->name]));
            }

            $nextWindow = $game->getNextWindowName();
            return redirect()->route('game.scouting', $gameId)
                ->with('success', __('messages.transfer_agreed', ['message' => $evaluation['message'], 'window' => $nextWindow]));
        }

        if ($evaluation['result'] === 'counter') {
            // Create pending counter-offer
            TransferOffer::create([
                'game_id' => $gameId,
                'game_player_id' => $playerId,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => $bidAmountCents,
                'asking_price' => $evaluation['counter_amount'],
                'offered_wage' => $wageDemand,
                'status' => TransferOffer::STATUS_PENDING,
                'expires_at' => $game->current_date->addDays(14),
                'game_date' => $game->current_date,
            ]);

            return redirect()->route('game.scouting', $gameId)
                ->with('success', $evaluation['message']);
        }

        // Rejected
        return redirect()->route('game.scouting', $gameId)
            ->with('error', $evaluation['message']);
    }
}
