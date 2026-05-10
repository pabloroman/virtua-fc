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

        // Calculate season stats per competition + 'all' so the sidebar can re-scope on filter
        $playedMatches = $realFixtures->filter(fn ($m) => $m->played);
        $competitions = $this->calendarService->getDistinctCompetitions($realFixtures);

        $statsByCompetition = ['all' => $this->calendarService->calculateSeasonStats($playedMatches, $game->team_id)];
        foreach ($competitions as $competition) {
            $statsByCompetition[$competition->id] = $this->calendarService->calculateSeasonStats(
                $playedMatches->filter(fn ($m) => $m->competition_id === $competition->id)->values(),
                $game->team_id,
            );
        }

        // Per-month list of competition ids — drives x-show visibility on month sections
        $monthsByCompetition = $calendar->map(fn ($matches) => $matches
            ->filter(fn ($m) => empty($m->is_placeholder) && $m->competition_id !== null)
            ->pluck('competition_id')
            ->unique()
            ->values()
            ->all()
        )->all();

        return view('calendar', [
            'game' => $game,
            'calendar' => $calendar,
            'competitions' => $competitions,
            'statsByCompetition' => $statsByCompetition,
            'monthsByCompetition' => $monthsByCompetition,
            'nextMatchId' => $nextMatchId,
        ]);
    }
}
