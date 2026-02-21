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
        $calendar = $this->calendarService->groupByMonth($fixtures);
        $nextMatchId = $fixtures->first(fn ($m) => !$m->played)?->id;

        // Calculate season stats from played fixtures
        $playedMatches = $fixtures->filter(fn($m) => $m->played);
        $seasonStats = $this->calendarService->calculateSeasonStats($playedMatches, $game->team_id);

        return view('calendar', [
            'game' => $game,
            'calendar' => $calendar,
            'seasonStats' => $seasonStats,
            'nextMatchId' => $nextMatchId,
        ]);
    }
}
