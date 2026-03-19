<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Report\Services\CompetitionSummaryService;

class ShowTournamentEnd
{
    public function __construct(
        private readonly CompetitionSummaryService $competitionSummaryService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if(!$game->isTournamentMode(), 404);

        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('season.tournament_not_complete'));
        }

        $data = $this->competitionSummaryService->buildTournamentSummary($game);

        return view('tournament-end', ['game' => $game, ...$data]);
    }
}
