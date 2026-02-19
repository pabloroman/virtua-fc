<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Support\Money;

class AcceptRenewalCounter
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

        $negotiation = RenewalNegotiation::where('game_player_id', $player->id)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_COUNTERED)
            ->firstOrFail();

        $success = $this->contractService->acceptCounterOffer($negotiation);

        if ($success) {
            return redirect()->route('game.transfers', $gameId)
                ->with('success', __('messages.renewal_agreed', [
                    'player' => $player->name,
                    'years' => $negotiation->fresh()->contract_years,
                    'wage' => Money::format($negotiation->counter_offer),
                ]));
        }

        return redirect()->route('game.transfers', $gameId)
            ->with('error', __('messages.renewal_failed'));
    }
}
