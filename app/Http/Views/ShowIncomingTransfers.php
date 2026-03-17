<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\TransferHeaderService;
use App\Models\Game;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class ShowIncomingTransfers
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly TransferHeaderService $headerService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Incoming transfer data
        $pendingBids = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('game_date')
            ->get();

        // Separate counter-offers from regular pending bids
        $counterOffers = $pendingBids->filter(function ($bid) {
            return $bid->asking_price && $bid->asking_price > $bid->transfer_fee;
        });
        $regularPendingBids = $pendingBids->reject(function ($bid) {
            return $bid->asking_price && $bid->asking_price > $bid->transfer_fee;
        });

        $recentSignings = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('resolved_at')
            ->get();

        $rejectedBids = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_REJECTED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('resolved_at', '>=', $game->current_date->subDays(7))
            ->orderByDesc('resolved_at')
            ->get();

        $incomingAgreedTransfers = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('game_date')
            ->get();

        // Loans in
        $loans = $this->loanService->getActiveLoans($game);
        $loansIn = $loans['in'];

        return view('incoming-transfers', [
            'game' => $game,
            'counterOffers' => $counterOffers,
            'pendingBids' => $regularPendingBids,
            'rejectedBids' => $rejectedBids,
            'recentSignings' => $recentSignings,
            'incomingAgreedTransfers' => $incomingAgreedTransfers,
            'loansIn' => $loansIn,
            ...$this->headerService->getHeaderData($game),
        ]);
    }
}
