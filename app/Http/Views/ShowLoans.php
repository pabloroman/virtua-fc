<?php

namespace App\Http\Views;

use App\Game\Services\LoanService;
use App\Models\Game;
use App\Models\GamePlayer;

class ShowLoans
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        $loans = $this->loanService->getActiveLoans($game);

        $loanSearches = GamePlayer::with(['player'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LOAN_SEARCH)
            ->get();

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('loans', [
            'game' => $game,
            'loansIn' => $loans['in'],
            'loansOut' => $loans['out'],
            'loanSearches' => $loanSearches,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
        ]);
    }
}
