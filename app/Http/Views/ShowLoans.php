<?php

namespace App\Http\Views;

use App\Game\Services\LoanService;
use App\Models\Game;

class ShowLoans
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        $loans = $this->loanService->getActiveLoans($game);

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('loans', [
            'game' => $game,
            'loansIn' => $loans['in'],
            'loansOut' => $loans['out'],
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
        ]);
    }
}
