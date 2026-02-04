<?php

namespace App\Http\Actions;

use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptTransferOffer
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $offerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        $offer = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('id', $offerId)
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->firstOrFail();

        // Verify the player belongs to the user's team
        if ($offer->gamePlayer->team_id !== $game->team_id) {
            abort(403, 'You can only accept offers for your own players.');
        }

        $playerName = $offer->gamePlayer->player->name;
        $teamName = $offer->offeringTeam->name;
        $fee = $offer->formatted_transfer_fee;
        $nextWindow = $game->getNextWindowName();

        $this->transferService->acceptOffer($offer);

        return redirect()
            ->route('game.transfers', $gameId)
            ->with('success', "Deal agreed! {$playerName} will join {$teamName} for {$fee} at the {$nextWindow} transfer window.");
    }
}
