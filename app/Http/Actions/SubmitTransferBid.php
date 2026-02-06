<?php

namespace App\Http\Actions;

use App\Game\Services\ScoutingService;
use App\Game\Services\TransferService;
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
            return redirect()->route('game.scouting.player', [$gameId, $playerId])
                ->with('error', 'Bid exceeds your transfer budget.');
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
            ]);

            // Complete immediately if window open, otherwise mark as agreed
            $completedImmediately = $this->transferService->acceptIncomingOffer($offer);

            if ($completedImmediately) {
                return redirect()->route('game.scouting', $gameId)
                    ->with('success', "Transfer complete! {$player->player->name} has joined your squad.");
            }

            $nextWindow = $game->getNextWindowName();
            return redirect()->route('game.scouting', $gameId)
                ->with('success', $evaluation['message'] . " The transfer will complete when the {$nextWindow} window opens.");
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
            ]);

            return redirect()->route('game.scouting.player', [$gameId, $playerId])
                ->with('success', $evaluation['message']);
        }

        // Rejected
        return redirect()->route('game.scouting.player', [$gameId, $playerId])
            ->with('error', $evaluation['message']);
    }
}
