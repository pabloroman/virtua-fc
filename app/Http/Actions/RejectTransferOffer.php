<?php

namespace App\Http\Actions;

use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RejectTransferOffer
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
            abort(403, 'You can only reject offers for your own players.');
        }

        if (! $offer->gamePlayer->isUserOwned($game)) {
            abort(403, 'Cannot reject offers for loaned players.');
        }

        // A bid that meets the release clause is a forced buyout — the user has no
        // veto. New clause-meeting offers are created as forced sales upstream and
        // never reach this action, but this guards any pre-existing pending offer.
        if ($game->release_clauses_enabled
            && $offer->gamePlayer->hasReleaseClause()
            && $offer->transfer_fee >= (int) $offer->gamePlayer->release_clause) {
            return redirect()
                ->route('game.transfers.outgoing', $gameId)
                ->with('error', __('messages.cannot_reject_release_clause_offer', [
                    'player' => $offer->gamePlayer->name,
                ]));
        }

        $team = $offer->offeringTeam;

        $this->transferService->rejectOffer($offer);

        return redirect()
            ->route('game.transfers.outgoing', $gameId)
            ->with('success', __('messages.offer_rejected', ['team_de' => $team->nameWithDe()]));
    }
}
