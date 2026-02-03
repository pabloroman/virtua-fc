<?php

namespace App\Http\Views;

use App\Game\Services\CalendarService;
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

        return view('calendar', [
            'game' => $game,
            'calendar' => $calendar,
        ]);
    }
}
