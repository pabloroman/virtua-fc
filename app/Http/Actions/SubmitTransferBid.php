<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class SubmitTransferBid
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
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
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.bid_exceeds_budget'));
        }

        $wageDemand = $this->scoutingService->calculateWageDemand($player);

        // Create a pending offer — evaluation deferred to next matchday
        TransferOffer::create([
            'game_id' => $gameId,
            'game_player_id' => $playerId,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => $bidAmountCents,
            'offered_wage' => $wageDemand,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $game->current_date->addDays(30),
            'game_date' => $game->current_date,
        ]);

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.bid_submitted', ['player' => $player->player->name]));
    }
}
