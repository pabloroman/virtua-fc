<?php

namespace App\Http\Actions;

use App\Game\Commands\AdvanceMatchday as AdvanceMatchdayCommand;
use App\Game\DTO\MatchEventData;
use App\Game\Game as GameAggregate;
use App\Game\Services\MatchSimulator;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Get next matchday number
        $nextMatchday = $game->current_matchday + 1;

        // Get all matches for this matchday
        $matches = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->where('round_number', $nextMatchday)
            ->where('played', false)
            ->get();

        if ($matches->isEmpty()) {
            // No more matches - season is complete
            return redirect()->route('show-game', $gameId)
                ->with('message', 'Season complete!');
        }

        // Get the date for this matchday (use first match's date)
        $matchdayDate = $matches->first()->scheduled_date->toDateString();

        // Load all game players for this game, grouped by team
        $allPlayers = GamePlayer::where('game_id', $gameId)->get()->groupBy('team_id');

        // Simulate all matches
        $matchResults = [];
        foreach ($matches as $match) {
            $homePlayers = $allPlayers->get($match->home_team_id, collect());
            $awayPlayers = $allPlayers->get($match->away_team_id, collect());

            $result = $this->matchSimulator->simulate(
                $match->homeTeam,
                $match->awayTeam,
                $homePlayers,
                $awayPlayers,
            );

            // Convert events to array format for storage
            $eventsArray = $result->events->map(fn (MatchEventData $e) => $e->toArray())->all();

            $matchResults[] = [
                'matchId' => $match->id,
                'homeTeamId' => $match->home_team_id,
                'awayTeamId' => $match->away_team_id,
                'homeScore' => $result->homeScore,
                'awayScore' => $result->awayScore,
                'competitionId' => $match->competition_id,
                'events' => $eventsArray,
            ];
        }

        // Create command and advance the game
        $command = new AdvanceMatchdayCommand(
            matchday: $nextMatchday,
            currentDate: $matchdayDate,
            matchResults: $matchResults,
        );

        $aggregate = GameAggregate::retrieve($gameId);
        $aggregate->advanceMatchday($command);

        return redirect()->route('game.results', [
            'gameId' => $gameId,
            'matchday' => $nextMatchday,
        ]);
    }
}
