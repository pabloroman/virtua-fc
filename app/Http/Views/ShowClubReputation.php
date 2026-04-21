<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Manager\Services\CareerSummaryService;
use App\Modules\Reputation\Services\ReputationSummaryService;

class ShowClubReputation
{
    public function __construct(
        private readonly ReputationSummaryService $reputationSummaryService,
        private readonly CareerSummaryService $careerSummaryService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team.clubProfile')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        return view('club.reputation', [
            'game' => $game,
            'summary' => $this->reputationSummaryService->build($game),
            'career' => $this->careerSummaryService->build($game, (int) auth()->id()),
        ]);
    }
}
