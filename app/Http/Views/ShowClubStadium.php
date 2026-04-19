<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Stadium\Services\StadiumSummaryService;

class ShowClubStadium
{
    public function __construct(
        private readonly StadiumSummaryService $stadiumSummaryService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        return view('club.stadium', [
            'game' => $game,
            'summary' => $this->stadiumSummaryService->build($game),
        ]);
    }
}
