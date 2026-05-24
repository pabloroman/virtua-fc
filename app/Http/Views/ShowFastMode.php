<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CompetitionViewService;
use App\Modules\Match\Services\FastModeService;
use App\Modules\Match\Services\MatchdayService;
use App\Models\Competition;
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
            || $game->isProcessingCareerActions()
            || $game->isAdvancingMatchday()
            || $game->matchday_advance_result
            || $game->pending_finalization_match_id
        ) {
            return redirect()->route('show-game', $gameId);
        }

        $lastMatch = $this->fastModeService->getLastPlayerMatch($game);
        $nextMatch = $this->loadNextPlayerMatch($game);

        // Tournament mode jumps straight to tournament-end. Season-based
        // modes fall through and render the page so the user can see the
        // score of their final simulated match; the template swaps the
        // Advance CTA for a Continue button that exits fast mode and lands
        // on the season-complete dashboard preview.
        if (! $nextMatch && ! $game->matches()->where('played', false)->exists() && $game->isTournamentMode()) {
            return redirect()->route('game.tournament-end', $gameId);
        }

        // Focus the standings panel on the competition just played; fall
        // back to the primary league when there's no last match yet.
        $focalCompetition = $lastMatch?->competition ?? $game->competition;
        $panelData = $this->buildPanelData($game, $focalCompetition, $lastMatch);

        return view('fast-mode', [
            'game' => $game,
            'lastMatch' => $lastMatch,
            'nextMatch' => $nextMatch,
            'focalCompetition' => $focalCompetition,
            'displayMode' => $panelData['displayMode'],
            'standings' => $panelData['standings'],
            'playerStanding' => $panelData['standings']?->firstWhere('team_id', $game->team_id),
            'rounds' => $panelData['rounds'],
            'tiesByRound' => $panelData['tiesByRound'],
            'currentRoundNumber' => $panelData['currentRoundNumber'],
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

    /**
     * Prepare standings or condensed-bracket data for the focal competition.
     * Bracket view triggers when the just-played match was a knockout tie
     * (covers knockout_cup competitions and the knockout phase of swiss_format
     * / group_stage_cup / league_with_playoff). Otherwise show abridged
     * standings.
     */
    private function buildPanelData(Game $game, Competition $competition, ?GameMatch $lastMatch): array
    {
        $playedKnockoutTie = $lastMatch?->isCupMatch() === true;
        $isPureKnockout = $competition->handler_type === 'knockout_cup';

        if ($playedKnockoutTie || $isPureKnockout) {
            $rounds = $this->competitionViewService->getKnockoutRounds($competition, $game->season);
            $tiesByRound = $this->competitionViewService->getKnockoutTies($game, $competition);

            // Anchor on the round of the just-played match when available.
            // findPlayerTie() returns the player's *latest* tie in the
            // competition, which is already the next round if the draw for
            // it has happened — that would skip the round just played.
            if ($playedKnockoutTie) {
                $currentRoundNumber = $lastMatch->round_number;
            } else {
                $playerTie = $this->competitionViewService->findPlayerTie($rounds, $tiesByRound, $game->team_id);
                $currentRoundNumber = $playerTie?->round_number
                    ?? $rounds->first(fn ($r) => $tiesByRound->has($r->round))?->round
                    ?? $rounds->first()?->round;
            }

            return [
                'displayMode' => 'bracket',
                'standings' => null,
                'rounds' => $rounds,
                'tiesByRound' => $tiesByRound,
                'currentRoundNumber' => $currentRoundNumber,
            ];
        }

        return [
            'displayMode' => 'standings',
            'standings' => $this->competitionViewService->getAbridgedStandings($game, $competition),
            'rounds' => null,
            'tiesByRound' => null,
            'currentRoundNumber' => null,
        ];
    }
}
