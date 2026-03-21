<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\TournamentSummary;
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

        // If game is being deleted, redirect to the snapshot
        if ($game->deleting_at) {
            $summary = TournamentSummary::where('user_id', $game->user_id)
                ->where('team_id', $game->team_id)
                ->where('competition_id', $game->competition_id)
                ->latest('created_at')
                ->first();

            if ($summary) {
                return redirect()->route('tournament-summary.show', $summary->id);
            }

            return redirect()->route('dashboard');
        }

        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('season.tournament_not_complete'));
        }

        $data = $this->competitionSummaryService->buildTournamentSummary($game);

        return view('tournament-end', ['game' => $game, ...$data]);
    }
}
