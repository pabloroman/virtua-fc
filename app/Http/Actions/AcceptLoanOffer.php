<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\LoanService;
use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptLoanOffer
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $offerId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        $offer = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('id', $offerId)
            ->where('game_id', $gameId)
            ->where('offer_type', TransferOffer::TYPE_LOAN_OUT)
            ->where('direction', TransferOffer::DIRECTION_OUTGOING)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->firstOrFail();

        // Verify the player belongs to the user's team
        if ($offer->gamePlayer->team_id !== $game->team_id) {
            abort(403, 'You can only accept loan offers for your own players.');
        }

        if ($offer->gamePlayer->isLoanedIn($game->team_id)) {
            abort(403, 'Cannot accept loan offers for loaned players.');
        }

        $playerName = $offer->gamePlayer->player->name;
        $team = $offer->offeringTeam;
        $windowOpen = $game->isTransferWindowOpen();

        $this->loanService->acceptLoanOffer($offer, $game);

        if ($windowOpen) {
            $message = __('messages.loan_offer_accepted', [
                'player' => $playerName,
                'team_a' => $team->nameWithA(),
            ]);
        } else {
            $message = __('messages.loan_offer_accepted_pre_window', [
                'player' => $playerName,
                'team' => $team->name,
                'window' => $game->getNextWindowName(),
            ]);
        }

        return redirect()
            ->route('game.transfers.outgoing', $gameId)
            ->with('success', $message);
    }
}
