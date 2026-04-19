<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Reputation\Services\ReputationSummaryService;

class ShowClubReputation
{
    public function __construct(
        private readonly ReputationSummaryService $reputationSummaryService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        return view('club.reputation', [
            'game' => $game,
            'summary' => $this->reputationSummaryService->build($game),
        ]);
    }
}
