<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\LoanService;
use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RejectLoanOffer
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

        if ($offer->gamePlayer->team_id !== $game->team_id) {
            abort(403, 'You can only reject loan offers for your own players.');
        }

        $team = $offer->offeringTeam;

        $this->loanService->rejectLoanOffer($offer, $game);

        return redirect()
            ->route('game.transfers.outgoing', $gameId)
            ->with('success', __('messages.loan_offer_rejected', ['team_de' => $team->nameWithDe()]));
    }
}
