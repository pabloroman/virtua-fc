<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Modules\Competition\Services\CompetitionViewService;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Match\Services\MatchdayService;
use App\Modules\Match\Services\MatchFinalizationService;
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
        private readonly MatchFinalizationService $finalizationService,
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

        // Mandatory pre-season setup: the player must choose their friendlies
        // before reaching the dashboard. Fires once the season transition has
        // finished (so the game is fully built) — after "Begin Season" for
        // transitions, and right after the welcome tutorial for new careers.
        if ($game->needsPreseasonOpponentSelection()) {
            return redirect()->route('game.preseason-setup', $gameId);
        }

        // Consume a completed matchday advance before any background-job
        // loading screens. When the user just advanced into a live match,
        // remaining AI batches and career actions often process in the
        // background — the live-match view has its own polling for those
        // (processingStatusUrl), so the user can watch the match while the
        // background work continues. Gating entry into the live match on
        // those flags would show an unwanted "just the user's crest"
        // loading screen after the advance overlay.
        if ($advanceResult = $game->matchday_advance_result) {
            $game->update(['matchday_advance_result' => null]);
            $result = MatchdayAdvanceResult::fromArray($advanceResult);

            return match ($result->type) {
                'live_match' => redirect()->route('game.live-match', [
                    'gameId' => $gameId,
                    'matchId' => $result->matchId,
                ]),
                // Tournament mode goes straight to tournament-end (no
                // "between matches" dashboard exists). Season-based modes
                // fall through to render the dashboard with $nextMatch=null
                // so the user can browse their club one last time before
                // committing to the season transition.
                'season_complete' => $game->isTournamentMode()
                    ? redirect()->route('game.tournament-end', $gameId)
                    : redirect()->route('show-game', $gameId),
                'done' => redirect()->route('show-game', $gameId),
                'blocked' => $result->pendingAction && $result->pendingAction['route']
                    ? redirect()->route($result->pendingAction['route'], $gameId)->with('warning', __('messages.action_required'))
                    : redirect()->route('show-game', $gameId)->with('warning', __('messages.action_required')),
            };
        }

        // Safety net: if the user abandoned a live match without clicking
        // Continue (back button, browser close, etc.) and never triggered
        // another advance, the match stays played=true with standings
        // unapplied. MatchdayOrchestrator's own finalizePendingMatch only
        // fires on the next advance(), which never happens at end-of-season.
        // Refresh $game afterward because finalize() may advance current_date
        // and generate new matches.
        if ($game->pending_finalization_match_id) {
            $this->finalizationService->finalizePendingIfAny($gameId);
            $game = $game->refresh();
        }

        // Show loading screen while career actions are processing in background
        $game->clearStuckCareerActions();
        if ($game->isProcessingCareerActions()) {
            return view('game-loading', [
                'game' => $game,
                'title' => __('game.processing_career_actions'),
                'message' => __('game.processing_career_actions_message'),
                'showCrest' => true,
            ]);
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

            // User's team has finished the season but other competitions
            // still have AI-only fixtures to simulate. Use a generic screen —
            // no club crest, no "teams warming up" copy — because the user
            // isn't playing.
            return view('game-loading', [
                'game' => $game,
                'title' => __('game.simulating_other_matches'),
                'message' => __('game.simulating_other_matches_message'),
                'showCrest' => false,
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

        // Tournament complete: redirect to tournament-end. Season-based
        // modes fall through and render the dashboard with $nextMatch=null
        // so the user can browse their club before clicking through to the
        // season summary (the irreversible "Start New Season" lives there).
        if (!$nextMatch && !$hasRemainingMatches && $game->isTournamentMode()) {
            return redirect()->route('game.tournament-end', $gameId);
        }

        $notifications = $this->notificationService->getNotifications($game->id, true, 15);
        $groupedNotifications = $notifications->groupBy(fn ($n) => $n->game_date?->format('Y-m-d') ?? 'unknown');

        $dashboardContext = $this->competitionViewService->resolveDashboardContext($game, $nextMatch);

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
            'dashboardContext' => $dashboardContext,
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

        // Add pre-season flag (hides the standings/cup-path card on the dashboard).
        if ($game->isInPreSeason()) {
            $viewData['isPreSeason'] = true;
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
