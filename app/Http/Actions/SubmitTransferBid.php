<?php

namespace App\Http\Actions;

use App\Game\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubmitTransferBid
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::with('finances')->findOrFail($gameId);
        $player = GamePlayer::with(['player', 'team'])->findOrFail($playerId);

        $validated = $request->validate([
            'bid_amount' => 'required|numeric|min:0',
        ]);

        $bidAmountCents = (int) ($validated['bid_amount'] * 100);

        // Budget validation
        $finances = $game->finances;
        if ($finances && $bidAmountCents > $finances->transfer_budget) {
            return redirect()->route('game.scouting.player', [$gameId, $playerId])
                ->with('error', 'Bid exceeds your transfer budget.');
        }

        // Evaluate bid
        $evaluation = $this->scoutingService->evaluateBid($player, $bidAmountCents);
        $wageDemand = $this->scoutingService->calculateWageDemand($player);

        if ($evaluation['result'] === 'accepted') {
            // Create agreed offer
            TransferOffer::create([
                'game_id' => $gameId,
                'game_player_id' => $playerId,
                'offering_team_id' => $game->team_id,
                'selling_team_id' => $player->team_id,
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'direction' => TransferOffer::DIRECTION_INCOMING,
                'transfer_fee' => $bidAmountCents,
                'asking_price' => $evaluation['asking_price'],
                'offered_wage' => $wageDemand,
                'status' => TransferOffer::STATUS_AGREED,
                'expires_at' => $game->current_date->addDays(30),
            ]);

            return redirect()->route('game.scouting.player', [$gameId, $playerId])
                ->with('success', $evaluation['message']);
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
