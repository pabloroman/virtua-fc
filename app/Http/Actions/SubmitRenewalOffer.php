<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Support\Money;
use Illuminate\Http\Request;

class SubmitRenewalOffer
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::findOrFail($gameId);
        $player = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->findOrFail($playerId);

        $offerWageEuros = (int) $request->input('offer_wage');
        $offeredYears = (int) $request->input('offered_years', 3);
        $offerWageCents = $offerWageEuros * 100;

        if ($offerWageCents <= 0) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.renewal_invalid_offer'));
        }

        // Check if this is a new offer on an existing countered negotiation
        $existingNegotiation = RenewalNegotiation::where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
            ->first();

        if ($existingNegotiation) {
            // Submit new round offer
            $this->contractService->submitNewOffer($existingNegotiation, $offerWageCents, $offeredYears);

            return redirect()->route('game.transfers', $gameId)
                ->with('success', __('messages.renewal_offer_submitted', [
                    'player' => $player->name,
                    'wage' => Money::format($offerWageCents),
                ]));
        }

        // New negotiation
        if (!$player->canBeOfferedRenewal()) {
            return redirect()->route('game.transfers', $gameId)
                ->with('error', __('messages.cannot_renew'));
        }

        $this->contractService->initiateNegotiation($player, $offerWageCents, $offeredYears);

        return redirect()->route('game.transfers', $gameId)
            ->with('success', __('messages.renewal_offer_submitted', [
                'player' => $player->name,
                'wage' => Money::format($offerWageCents),
            ]));
    }
}
