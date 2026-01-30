<?php

namespace App\Http\Actions;

use App\Game\Commands\AdvanceMatchday as AdvanceMatchdayCommand;
use App\Game\DTO\MatchEventData;
use App\Game\Enums\Formation;
use App\Game\Game as GameAggregate;
use App\Game\Handlers\KnockoutCupHandler;
use App\Game\Services\CompetitionHandlerResolver;
use App\Game\Services\LineupService;
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
        private readonly LineupService $lineupService,
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
        $allPlayers = GamePlayer::with('player')->where('game_id', $gameId)->get()->groupBy('team_id');

        // Ensure all matches have lineups set (auto-select for AI teams)
        $this->ensureLineupsSet($matches, $game, $allPlayers);

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
     * Simulate a single match using lineup players.
     */
    private function simulateMatch(GameMatch $match, $allPlayers): array
    {
        // Get lineup players only (filter from all players)
        $homeLineupIds = $match->home_lineup ?? [];
        $awayLineupIds = $match->away_lineup ?? [];

        $allHomePlayers = $allPlayers->get($match->home_team_id, collect());
        $allAwayPlayers = $allPlayers->get($match->away_team_id, collect());

        // Filter to only lineup players if lineups are set
        $homePlayers = !empty($homeLineupIds)
            ? $allHomePlayers->filter(fn ($p) => in_array($p->id, $homeLineupIds))
            : $allHomePlayers;

        $awayPlayers = !empty($awayLineupIds)
            ? $allAwayPlayers->filter(fn ($p) => in_array($p->id, $awayLineupIds))
            : $allAwayPlayers;

        // Get formations (default to 4-4-2 if not set)
        $homeFormation = Formation::tryFrom($match->home_formation) ?? Formation::F_4_4_2;
        $awayFormation = Formation::tryFrom($match->away_formation) ?? Formation::F_4_4_2;

        $result = $this->matchSimulator->simulate(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeFormation,
            $awayFormation,
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

    /**
     * Ensure all matches have lineups set (auto-select for AI teams).
     * Uses the player's default formation for their team.
     */
    private function ensureLineupsSet($matches, Game $game, $allPlayers): void
    {
        // Get player's preferred formation
        $playerFormation = $game->default_formation
            ? Formation::tryFrom($game->default_formation)
            : null;

        foreach ($matches as $match) {
            $matchday = $match->round_number ?? $game->current_matchday + 1;
            $matchDate = $match->scheduled_date;

            // Auto-select home lineup if not set
            if (empty($match->home_lineup)) {
                // Use player's formation for their team, default for others
                $formation = ($match->home_team_id === $game->team_id)
                    ? $playerFormation
                    : null;

                $lineup = $this->lineupService->autoSelectLineup(
                    $game->id,
                    $match->home_team_id,
                    $matchDate,
                    $matchday,
                    $formation
                );
                $this->lineupService->saveLineup($match, $match->home_team_id, $lineup);

                // Save formation if it's the player's team
                if ($match->home_team_id === $game->team_id && $playerFormation) {
                    $this->lineupService->saveFormation($match, $match->home_team_id, $playerFormation->value);
                }
            }

            // Auto-select away lineup if not set
            if (empty($match->away_lineup)) {
                // Use player's formation for their team, default for others
                $formation = ($match->away_team_id === $game->team_id)
                    ? $playerFormation
                    : null;

                $lineup = $this->lineupService->autoSelectLineup(
                    $game->id,
                    $match->away_team_id,
                    $matchDate,
                    $matchday,
                    $formation
                );
                $this->lineupService->saveLineup($match, $match->away_team_id, $lineup);

                // Save formation if it's the player's team
                if ($match->away_team_id === $game->team_id && $playerFormation) {
                    $this->lineupService->saveFormation($match, $match->away_team_id, $playerFormation->value);
                }
            }
        }
    }
}
