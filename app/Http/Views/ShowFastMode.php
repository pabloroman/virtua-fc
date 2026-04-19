<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CompetitionViewService;
use App\Modules\Match\Services\FastModeService;
use App\Modules\Match\Services\MatchdayService;
use App\Models\Game;
use App\Models\GameMatch;

class ShowFastMode
{
    public function __construct(
        private readonly MatchdayService $matchdayService,
        private readonly FastModeService $fastModeService,
        private readonly CompetitionViewService $competitionViewService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        if (! $game->isFastMode()) {
            return redirect()->route('show-game', $gameId);
        }

        // Respect the same setup-completion gates that ShowGame enforces.
        if ($game->needsWelcome()) {
            return redirect()->route('game.welcome', $gameId);
        }

        if (! $game->isSetupComplete() || $game->needsNewSeasonSetup()) {
            return redirect()->route('game.new-season', $gameId);
        }

        // Transient states (transition, background processing, advancing, a
        // consumed matchday_advance_result, live-match finalization) are all
        // handled by ShowGame — bounce there and let it render loading
        // screens or redirect to live-match UI as appropriate. Safe from
        // redirect loops: ShowGame only redirects back to fast-mode after
        // those transient states have cleared.
        if (
            $game->isTransitioningSeason()
            || $game->isProcessingRemainingBatches()
            || $game->isProcessingCareerActions()
            || $game->isAdvancingMatchday()
            || $game->matchday_advance_result
            || $game->pending_finalization_match_id
        ) {
            return redirect()->route('show-game', $gameId);
        }

        $lastMatch = $this->fastModeService->getLastPlayerMatch($game);
        $nextMatch = $this->loadNextPlayerMatch($game);

        // No more matches — send the user to the season/tournament end screen.
        if (! $nextMatch && ! $game->matches()->where('played', false)->exists()) {
            return $game->isTournamentMode()
                ? redirect()->route('game.tournament-end', $gameId)
                : redirect()->route('game.season-end', $gameId);
        }

        $leagueStandings = $this->competitionViewService->getAbridgedLeagueStandings($game);
        $playerStanding = $leagueStandings->firstWhere('team_id', $game->team_id);

        return view('fast-mode', [
            'game' => $game,
            'lastMatch' => $lastMatch,
            'nextMatch' => $nextMatch,
            'leagueStandings' => $leagueStandings,
            'playerStanding' => $playerStanding,
            'pendingAction' => $game->getFirstPendingAction(),
        ]);
    }

    private function loadNextPlayerMatch(Game $game): ?GameMatch
    {
        $match = $this->matchdayService->getNextPlayerMatch($game);

        if ($match) {
            $match->load(['homeTeam', 'awayTeam', 'competition']);
        }

        return $match;
    }
}
