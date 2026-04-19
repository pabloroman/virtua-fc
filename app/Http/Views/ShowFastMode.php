<?php

namespace App\Http\Views;

use App\Modules\Match\Services\MatchdayService;
use App\Modules\Notification\Services\NotificationService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;

class ShowFastMode
{
    public function __construct(
        private readonly MatchdayService $matchdayService,
        private readonly NotificationService $notificationService,
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

        $lastMatch = $this->loadLastPlayerMatch($game);
        $nextMatch = $this->loadNextPlayerMatch($game);

        // No more matches — send the user to the season/tournament end screen.
        if (! $nextMatch && ! $game->matches()->where('played', false)->exists()) {
            return $game->isTournamentMode()
                ? redirect()->route('game.tournament-end', $gameId)
                : redirect()->route('game.season-end', $gameId);
        }

        $leagueStandings = $this->getLeagueStandings($game);
        $playerStanding = $leagueStandings->firstWhere('team_id', $game->team_id);

        $unreadNotificationCount = $this->notificationService->getUnreadCount($game->id);
        $pendingAction = $game->getFirstPendingAction();

        return view('fast-mode', [
            'game' => $game,
            'lastMatch' => $lastMatch,
            'nextMatch' => $nextMatch,
            'leagueStandings' => $leagueStandings,
            'playerStanding' => $playerStanding,
            'unreadNotificationCount' => $unreadNotificationCount,
            'pendingAction' => $pendingAction,
        ]);
    }

    private function loadLastPlayerMatch(Game $game): ?GameMatch
    {
        /** @var GameMatch|null $match */
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->orderByDesc('scheduled_date')
            ->first();

        return $match;
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
     * Window around the player's position plus the top 3 — matches the ShowGame
     * abridged standings logic so the user sees familiar context.
     */
    private function getLeagueStandings(Game $game): \Illuminate\Support\Collection
    {
        $query = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id);

        if ($game->isTournamentMode()) {
            $playerGroupLabel = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->where('team_id', $game->team_id)
                ->value('group_label');

            if ($playerGroupLabel) {
                $query->where('group_label', $playerGroupLabel);
            }
        }

        $standings = $query->orderBy('position')->get();

        if ($standings->isEmpty()) {
            return collect();
        }

        if ($game->isTournamentMode()) {
            return $standings;
        }

        $playerPosition = $standings->firstWhere('team_id', $game->team_id)?->position ?? 1;
        $windowStart = max(1, $playerPosition - 2);
        $windowEnd = min($standings->count(), $playerPosition + 2);

        $topIds = $standings->where('position', '<=', 3)->pluck('team_id');
        $windowIds = $standings->whereBetween('position', [$windowStart, $windowEnd])->pluck('team_id');
        $visibleIds = $topIds->merge($windowIds)->unique();

        return $standings->filter(fn ($s) => $visibleIds->contains($s->team_id))->values();
    }
}
