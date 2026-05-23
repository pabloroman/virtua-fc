<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Exceptions\SquadMinimumException;
use App\Modules\Transfer\Services\TransferService;
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

        $offer = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('id', $offerId)
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->firstOrFail();

        // Verify the player belongs to the user's organization (first team or reserve team)
        if (! $game->ownsTeam($offer->gamePlayer->team_id)) {
            abort(403, 'You can only accept offers for your own players.');
        }

        if (! $offer->gamePlayer->isUserOwned($game)) {
            abort(403, 'Cannot accept offers for loaned players.');
        }

        if ($offer->gamePlayer->isInSaleCooldown($game)) {
            return redirect()->back()->with('error', __('messages.cannot_sell_same_window', [
                'player' => $offer->gamePlayer->name,
            ]));
        }

        $playerName = $offer->gamePlayer->name;
        $team = $offer->offeringTeam;
        $fee = $offer->formatted_transfer_fee;

        try {
            $this->transferService->acceptOffer($offer);
        } catch (SquadMinimumException $e) {
            return redirect()->back()->with('error', $this->formatBreachMessage($e));
        }

        if ($game->isTransferWindowOpen()) {
            $message = __('messages.offer_accepted_intra_window', [
                'player' => $playerName,
                'team' => $team->name,
                'fee' => $fee,
            ]);
        } else {
            $nextWindow = $game->getNextWindowName();
            $message = __('messages.offer_accepted_pre_contract', [
                'player' => $playerName,
                'team' => $team->name,
                'fee' => $fee,
                'window' => $nextWindow,
            ]);
        }

        return redirect()
            ->route('game.transfers.outgoing', $gameId)
            ->with('success', $message);
    }

    private function formatBreachMessage(SquadMinimumException $e): string
    {
        if ($e->type() === 'too_small') {
            return __('messages.accept_offer_squad_too_small', ['min' => $e->min()]);
        }

        return __('messages.accept_offer_position_minimum', [
            'group' => __('squad.' . strtolower($e->group()) . 's'),
            'min'   => $e->min(),
        ]);
    }
}
