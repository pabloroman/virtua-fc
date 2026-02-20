<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Models\Game;
use Illuminate\Http\Request;

class ShowMatchResults
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $competition, int $matchday)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $roundName = $request->query('round');
        $matches = $this->calendarService->getMatchdayResults($gameId, $competition, $matchday, $roundName);
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
