<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Exceptions\SquadMinimumException;
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

        $offer = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('id', $offerId)
            ->where('game_id', $gameId)
            ->where('offer_type', TransferOffer::TYPE_LOAN_OUT)
            ->where('direction', TransferOffer::DIRECTION_OUTGOING)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->firstOrFail();

        // Verify the player belongs to the user's organization (first team or reserve team)
        if (! $game->ownsTeam($offer->gamePlayer->team_id)) {
            abort(403, 'You can only accept loan offers for your own players.');
        }

        if (! $offer->gamePlayer->isUserOwned($game)) {
            abort(403, 'Cannot accept loan offers for loaned players.');
        }

        $playerName = $offer->gamePlayer->name;
        $team = $offer->offeringTeam;

        try {
            $this->loanService->acceptLoanOffer($offer, $game);
        } catch (SquadMinimumException $e) {
            return redirect()->back()->with('error', $this->formatBreachMessage($e));
        }

        if ($game->isTransferWindowOpen()) {
            $message = __('messages.loan_offer_agreed_intra_window', [
                'player' => $playerName,
                'team' => $team->name,
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

    private function formatBreachMessage(SquadMinimumException $e): string
    {
        if ($e->type() === 'too_small') {
            return __('messages.accept_loan_squad_too_small', ['min' => $e->min()]);
        }

        return __('messages.accept_loan_position_minimum', [
            'group' => __('squad.' . strtolower($e->group()) . 's'),
            'min'   => $e->min(),
        ]);
    }
}
