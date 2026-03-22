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

        $activeNegotiations = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_FEE_AGREED])
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('game_date')
            ->get();

        $recentSignings = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
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
            'activeNegotiations' => $activeNegotiations,
            'recentSignings' => $recentSignings,
            'incomingAgreedTransfers' => $incomingAgreedTransfers,
            'loansIn' => $loansIn,
            ...$this->headerService->getHeaderData($game),
        ]);
    }
}
