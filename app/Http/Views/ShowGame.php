<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Modules\Competition\Services\CompetitionViewService;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Services\MatchdayService;
use App\Modules\Match\Services\MatchNarrativeService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;

class ShowGame
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly MatchdayService $matchdayService,
        private readonly MatchNarrativeService $narrativeService,
        private readonly NotificationService $notificationService,
        private readonly CompetitionViewService $competitionViewService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Redirect to welcome tutorial if not yet completed (new games only)
        if ($game->needsWelcome()) {
            return redirect()->route('game.welcome', $gameId);
        }

        // Redirect to new-season setup if setup or new-season setup not completed
        if (!$game->isSetupComplete() || $game->needsNewSeasonSetup()) {
            return redirect()->route('game.new-season', $gameId);
        }

        // Show loading screen while season transition runs in background
        if ($game->isTransitioningSeason()) {
            // Re-dispatch if stuck for > 2 minutes
            if ($game->season_transitioning_at->lt(now()->subMinutes(2))) {
                ProcessSeasonTransition::dispatch($game->id);
                $game->update(['season_transitioning_at' => now()]);
            }
            $isTournament = $game->isTournamentMode();
            return view('game-loading', [
                'game' => $game,
                'title' => $isTournament ? __('game.preparing_tournament') : __('game.preparing_season'),
                'message' => $isTournament ? __('game.setup_tournament_loading_message') : __('game.setup_loading_message'),
                'showCrest' => true,
            ]);
        }

        // Show loading screen while remaining batches are processing in background
        $game->clearStuckRemainingBatches();
        if ($game->isProcessingRemainingBatches()) {
            return view('game-loading', [
                'game' => $game,
                'title' => __('game.simulating_matches'),
                'message' => __('game.simulating_matches_message'),
                'showCrest' => true,
            ]);
        }

        // Show loading screen while career actions are processing in background
        $game->clearStuckCareerActions();
        if ($game->isProcessingCareerActions()) {
            return view('game-loading', [
                'game' => $game,
                'title' => __('game.processing_career_actions'),
                'message' => __('game.processing_career_actions_message'),
            ]);
        }

        // Matchday advance completed — consume result and redirect
        if ($advanceResult = $game->matchday_advance_result) {
            $game->update(['matchday_advance_result' => null]);
            $result = MatchdayAdvanceResult::fromArray($advanceResult);

            return match ($result->type) {
                'live_match' => redirect()->route('game.live-match', [
                    'gameId' => $gameId,
                    'matchId' => $result->matchId,
                ]),
                'season_complete' => redirect()->route($game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end', $gameId),
                'done' => redirect()->route('show-game', $gameId),
                'blocked' => $result->pendingAction && $result->pendingAction['route']
                    ? redirect()->route($result->pendingAction['route'], $gameId)->with('warning', __('messages.action_required'))
                    : redirect()->route('show-game', $gameId)->with('warning', __('messages.action_required')),
            };
        }

        // Show loading screen while matchday advance runs in background
        if ($game->isAdvancingMatchday()) {
            $nextMatch = $this->loadNextMatch($game);

            if ($nextMatch) {
                return view('game-loading-matchday', [
                    'game' => $game,
                    'nextMatch' => $nextMatch,
                ]);
            }

            return view('game-loading', [
                'game' => $game,
                'title' => __('game.simulating_matches'),
                'message' => __('game.simulating_matches_message'),
                'showCrest' => true,
            ]);
        }

        // Fast mode takes over the dashboard — redirect only after all
        // transient-state checks (transition/processing/advance) have been
        // handled above, to avoid redirect loops with ShowFastMode.
        // Live-match finalization still happens in the normal flow, so this
        // redirect is skipped when a match is pending finalization.
        if ($game->isFastMode() && ! $game->pending_finalization_match_id) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        $nextMatch = $this->loadNextMatch($game);
        $hasRemainingMatches = !$nextMatch && $game->matches()->where('played', false)->exists();

        // Tournament mode: auto-redirect to simulate remaining matches
        // when the player is eliminated (no next match but matches remain)
        if ($game->isTournamentMode() && !$nextMatch && $hasRemainingMatches) {
            return redirect()->route('game.simulate-tournament', $gameId);
        }

        // Season/tournament complete: redirect to end-of-season summary
        if (!$nextMatch && !$hasRemainingMatches) {
            return $game->isTournamentMode()
                ? redirect()->route('game.tournament-end', $gameId)
                : redirect()->route('game.season-end', $gameId);
        }

        $notifications = $this->notificationService->getNotifications($game->id, true, 15);
        $groupedNotifications = $notifications->groupBy(fn ($n) => $n->game_date?->format('Y-m-d') ?? 'unknown');

        $leagueStandings = $this->competitionViewService->getAbridgedLeagueStandings($game);

        $viewData = [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'hasRemainingMatches' => $hasRemainingMatches,
            'homeStanding' => $nextMatch ? GameStanding::forTeamInCompetition($game, $nextMatch->home_team_id, $nextMatch->competition_id) : null,
            'awayStanding' => $nextMatch ? GameStanding::forTeamInCompetition($game, $nextMatch->away_team_id, $nextMatch->competition_id) : null,
            'playerForm' => $this->calendarService->getTeamForm($game->id, $game->team_id),
            'opponentForm' => $this->getOpponentForm($game, $nextMatch),
            'upcomingFixtures' => $this->calendarService->getUpcomingFixtures($game),
            'groupedNotifications' => $groupedNotifications,
            'unreadNotificationCount' => $this->notificationService->getUnreadCount($game->id),
            'leagueStandings' => $leagueStandings,
        ];

        // Generate pre-match narrative snippets (tournament mode only for now)
        if ($nextMatch && $game->isTournamentMode()) {
            $isHome = $nextMatch->home_team_id === $game->team_id;
            $viewData['narratives'] = $this->narrativeService->generate(
                $game,
                $nextMatch,
                $isHome ? $viewData['homeStanding'] : $viewData['awayStanding'],
                $isHome ? $viewData['awayStanding'] : $viewData['homeStanding'],
                $viewData['playerForm'],
                $viewData['opponentForm'],
            );
        }

        // Add knockout progress for tournament mode
        if ($game->isTournamentMode()) {
            $viewData['tournamentTie'] = $this->getPlayerTournamentTie($game);

            if ($nextMatch?->cup_tie_id) {
                $viewData['nextRoundPreview'] = $this->getNextRoundPreview($nextMatch->cupTie);
            }
        }

        // Add pre-season data
        if ($game->isInPreSeason()) {
            $firstCompetitiveMatch = GameMatch::where('game_id', $game->id)
                ->where('competition_id', '!=', 'PRESEASON')
                ->where('played', false)
                ->orderBy('scheduled_date')
                ->first();

            $viewData['isPreSeason'] = true;
            $viewData['seasonStartDate'] = $firstCompetitiveMatch?->scheduled_date;
        }

        return view('game', $viewData);
    }

    private function loadNextMatch(Game $game): ?GameMatch
    {
        $nextMatch = $this->matchdayService->getNextPlayerMatch($game);

        if ($nextMatch) {
            $nextMatch->load(['homeTeam', 'awayTeam', 'competition']);
        }

        return $nextMatch;
    }

    private function getOpponentForm(Game $game, ?GameMatch $nextMatch): array
    {
        if (!$nextMatch) {
            return [];
        }

        $opponentId = $nextMatch->home_team_id === $game->team_id
            ? $nextMatch->away_team_id
            : $nextMatch->home_team_id;

        return $this->calendarService->getTeamForm($game->id, $opponentId);
    }

    private function getPlayerTournamentTie(Game $game): ?CupTie
    {
        return CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch'])
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->orderByDesc('round_number')
            ->first();
    }

    /**
     * Find the opposite tie in the bracket that determines the next-round opponent.
     *
     * Ties within a round are paired by bracket_position order: indices 0↔1, 2↔3, etc.
     * Returns an array with the opposite tie and, if resolved, the actual opponent team.
     *
     * @return array{tie: CupTie, opponent: ?Team}|null
     */
    private function getNextRoundPreview(CupTie $currentTie): ?array
    {
        $tiesInRound = CupTie::with(['homeTeam', 'awayTeam', 'winner'])
            ->where('game_id', $currentTie->game_id)
            ->where('competition_id', $currentTie->competition_id)
            ->where('round_number', $currentTie->round_number)
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get();

        if ($tiesInRound->count() < 2) {
            return null; // Final — no next round
        }

        $index = $tiesInRound->search(fn ($t) => $t->id === $currentTie->id);

        if ($index === false) {
            return null;
        }

        $oppositeIndex = ($index % 2 === 0) ? $index + 1 : $index - 1;
        $oppositeTie = $tiesInRound->get($oppositeIndex);

        if (! $oppositeTie) {
            return null;
        }

        return [
            'tie' => $oppositeTie,
            'opponent' => $oppositeTie->completed ? $oppositeTie->winner : null,
        ];
    }

}
