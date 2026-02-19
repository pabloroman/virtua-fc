<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Models\Game;

class ShowMatchResults
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function __invoke(string $gameId, string $competition, int $matchday)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $matches = $this->calendarService->getMatchdayResults($gameId, $competition, $matchday);
        $playerMatch = $this->calendarService->findPlayerMatch($matches, $game->team_id);

        return view('results', [
            'game' => $game,
            'competition' => $matches->first()?->competition,
            'matchday' => $matchday,
            'matches' => $matches,
            'playerMatch' => $playerMatch,
            'calendarUrl' => route('game.calendar', $gameId),
        ]);
    }
}
