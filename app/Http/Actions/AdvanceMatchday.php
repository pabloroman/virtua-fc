<?php

namespace App\Http\Actions;

use App\Game\Commands\AdvanceMatchday as AdvanceMatchdayCommand;
use App\Game\DTO\MatchEventData;
use App\Game\Game as GameAggregate;
use App\Game\Handlers\KnockoutCupHandler;
use App\Game\Services\CompetitionHandlerResolver;
use App\Game\Services\MatchSimulator;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
        private readonly CompetitionHandlerResolver $handlerResolver,
        private readonly KnockoutCupHandler $cupHandler,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Find the next unplayed match to determine target date
        $nextMatch = GameMatch::where('game_id', $gameId)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        if (!$nextMatch) {
            return redirect()->route('show-game', $gameId)
                ->with('message', 'Season complete!');
        }

        // Conduct any pending cup draws based on the target date
        // This must happen BEFORE we determine which match to play,
        // as the draw may create cup matches that are scheduled earlier
        $this->cupHandler->beforeMatches($game, $nextMatch->scheduled_date->toDateString());

        // Re-fetch the next match in case cup matches were created
        $nextMatch = GameMatch::where('game_id', $gameId)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        if (!$nextMatch) {
            return redirect()->route('show-game', $gameId)
                ->with('message', 'Season complete!');
        }

        // Get the handler for this competition type
        $competition = Competition::find($nextMatch->competition_id);
        $handler = $this->handlerResolver->resolve($competition);

        // Pre-match actions (handler-specific, e.g., additional draws for later rounds)
        $handler->beforeMatches($game, $nextMatch->scheduled_date->toDateString());

        // Get the batch of matches to play
        $matches = $handler->getMatchBatch($gameId, $nextMatch);

        if ($matches->isEmpty()) {
            return redirect()->route('show-game', $gameId)
                ->with('message', 'No matches to play.');
        }

        // For matchdays spanning multiple days, use the latest date
        $currentDate = $matches->max('scheduled_date')->toDateString();

        // Load all game players for this game, grouped by team
        $allPlayers = GamePlayer::where('game_id', $gameId)->get()->groupBy('team_id');

        // Simulate all matches
        $matchResults = [];
        foreach ($matches as $match) {
            $matchResults[] = $this->simulateMatch($match, $allPlayers);
        }

        // Determine matchday number
        $matchday = $matches->first()->round_number ?? $game->current_matchday;

        // Create command and advance the game
        $command = new AdvanceMatchdayCommand(
            matchday: $matchday,
            currentDate: $currentDate,
            matchResults: $matchResults,
        );

        $aggregate = GameAggregate::retrieve($gameId);
        $aggregate->advanceMatchday($command);

        // Post-match actions (e.g., resolve cup ties)
        $handler->afterMatches($game, $matches, $allPlayers);

        // Redirect based on competition type
        return redirect()->to($handler->getRedirectRoute($game, $matches, $matchday));
    }

    /**
     * Simulate a single match.
     */
    private function simulateMatch(GameMatch $match, $allPlayers): array
    {
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

        return [
            'matchId' => $match->id,
            'homeTeamId' => $match->home_team_id,
            'awayTeamId' => $match->away_team_id,
            'homeScore' => $result->homeScore,
            'awayScore' => $result->awayScore,
            'competitionId' => $match->competition_id,
            'events' => $eventsArray,
        ];
    }
}
