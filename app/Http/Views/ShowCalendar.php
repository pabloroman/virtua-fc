<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Models\Game;

class ShowCalendar
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $fixtures = $this->calendarService->getTeamFixtures($game);

        // In tournament mode, add placeholder rows for knockout rounds not yet drawn
        if ($game->isTournamentMode() && $game->competition_id) {
            $placeholders = $this->calendarService->getKnockoutPlaceholders($game, $game->competition_id);
            $fixtures = $fixtures->concat($placeholders)->sortBy('scheduled_date')->values();
        }

        $calendar = $this->calendarService->groupByMonth($fixtures);
        $realFixtures = $fixtures->filter(fn ($m) => empty($m->is_placeholder));
        $nextMatchId = $realFixtures->first(fn ($m) => !$m->played)?->id;

        // Calculate season stats from played fixtures
        $playedMatches = $realFixtures->filter(fn($m) => $m->played);
        $seasonStats = $this->calendarService->calculateSeasonStats($playedMatches, $game->team_id);

        return view('calendar', [
            'game' => $game,
            'calendar' => $calendar,
            'seasonStats' => $seasonStats,
            'nextMatchId' => $nextMatchId,
        ]);
    }
}
