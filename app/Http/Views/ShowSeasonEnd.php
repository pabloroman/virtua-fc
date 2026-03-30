<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Report\Services\SeasonSummaryService;

class ShowSeasonEnd
{
    public function __construct(
        private readonly SeasonSummaryService $seasonSummaryService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        if ($game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        $unplayedMatches = $game->matches()
            ->where('played', false)
            ->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', 'Season is not complete yet.');
        }

        $data = $this->seasonSummaryService->buildSeasonSummary($game);

        return view('season-end', ['game' => $game, ...$data]);
    }
}
