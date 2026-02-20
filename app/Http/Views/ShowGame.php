<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Modules\Notification\Services\NotificationService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;

class ShowGame
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly NotificationService $notificationService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Redirect to welcome tutorial if not yet completed (new games only)
        if ($game->needsWelcome()) {
            return redirect()->route('game.welcome', $gameId);
        }

        // Redirect to onboarding if setup or onboarding not completed
        if (!$game->isSetupComplete() || $game->needsOnboarding()) {
            return redirect()->route('game.onboarding', $gameId);
        }

        $nextMatch = $this->loadNextMatch($game);
        $hasRemainingMatches = !$nextMatch && $game->matches()->where('played', false)->exists();

        $notifications = $this->notificationService->getNotifications($game->id, true, 15);
        $groupedNotifications = $notifications->groupBy(fn ($n) => $n->game_date?->format('Y-m-d') ?? 'unknown');

        return view('game', [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'hasRemainingMatches' => $hasRemainingMatches,
            'homeStanding' => $nextMatch ? $this->getTeamStanding($game, $nextMatch->home_team_id, $nextMatch->competition_id) : null,
            'awayStanding' => $nextMatch ? $this->getTeamStanding($game, $nextMatch->away_team_id, $nextMatch->competition_id) : null,
            'playerForm' => $this->calendarService->getTeamForm($game->id, $game->team_id),
            'opponentForm' => $this->getOpponentForm($game, $nextMatch),
            'upcomingFixtures' => $this->calendarService->getUpcomingFixtures($game),
            'groupedNotifications' => $groupedNotifications,
            'unreadNotificationCount' => $this->notificationService->getUnreadCount($game->id),
        ]);
    }

    private function loadNextMatch(Game $game): ?GameMatch
    {
        $nextMatch = $game->next_match;

        if ($nextMatch) {
            $nextMatch->load(['homeTeam', 'awayTeam', 'competition']);
        }

        return $nextMatch;
    }

    private function getTeamStanding(Game $game, string $teamId, string $competitionId): ?GameStanding
    {
        $standing = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->first();

        // Fall back to primary league standing for cup matches
        if (!$standing && $competitionId !== $game->competition_id) {
            $standing = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->where('team_id', $teamId)
                ->first();
        }

        return $standing;
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
}
