<?php

namespace App\Game\Services;

use App\Game\Handlers\KnockoutCupHandler;
use App\Game\Handlers\LeagueWithPlayoffHandler;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class MatchdayService
{
    public function __construct(
        private readonly CompetitionHandlerResolver $handlerResolver,
        private readonly KnockoutCupHandler $cupHandler,
    ) {}

    /**
     * Get the next batch of matches to play, handling cup draws and competition logic.
     *
     * @return array{matches: Collection, handler: mixed, matchday: int, currentDate: string}|null
     */
    public function getNextMatchBatch(Game $game): ?array
    {
        $nextMatch = $this->findNextMatch($game->id);

        // If no match found, check if playoffs need to be generated
        if (!$nextMatch) {
            $this->checkForPendingPlayoffs($game);
            $nextMatch = $this->findNextMatch($game->id);
        }

        if (!$nextMatch) {
            return null;
        }

        // Conduct any pending cup draws (may create new matches)
        $this->cupHandler->beforeMatches($game, $nextMatch->scheduled_date->toDateString());

        // Re-fetch in case cup matches were created
        $nextMatch = $this->findNextMatch($game->id);

        if (!$nextMatch) {
            return null;
        }

        // Get competition handler
        $competition = Competition::find($nextMatch->competition_id);
        $handler = $this->handlerResolver->resolve($competition);

        // Pre-match actions (handler-specific)
        $handler->beforeMatches($game, $nextMatch->scheduled_date->toDateString());

        // Get the batch of matches to play
        $matches = $handler->getMatchBatch($game->id, $nextMatch);
        $matches->load(['competition', 'homeTeam', 'awayTeam']);

        if ($matches->isEmpty()) {
            return null;
        }

        // Determine matchday number
        $matchCompetition = $matches->first()?->competition;
        $isLeagueMatch = $matchCompetition?->type === 'league';
        $matchday = $isLeagueMatch
            ? ($matches->first()->round_number ?? $game->current_matchday)
            : $game->current_matchday;

        // For matchdays spanning multiple days, use the latest date
        $currentDate = $matches->max('scheduled_date')->toDateString();

        return [
            'matches' => $matches,
            'handler' => $handler,
            'matchday' => $matchday,
            'currentDate' => $currentDate,
        ];
    }

    /**
     * Find the next unplayed match.
     */
    private function findNextMatch(string $gameId): ?GameMatch
    {
        return GameMatch::where('game_id', $gameId)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();
    }

    /**
     * Check if any competition needs playoff matches generated.
     * This handles the case where regular season is complete but playoffs haven't started.
     */
    private function checkForPendingPlayoffs(Game $game): void
    {
        // Find competitions that use the league_with_playoff handler
        $playoffCompetitions = Competition::where('handler_type', 'league_with_playoff')->get();

        foreach ($playoffCompetitions as $competition) {
            // Check if this game has matches in this competition
            $hasMatches = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->exists();

            if (!$hasMatches) {
                continue;
            }

            // Get the handler and trigger beforeMatches to generate playoffs if needed
            $handler = $this->handlerResolver->resolve($competition);

            if ($handler instanceof LeagueWithPlayoffHandler) {
                // Use current date or a future date for playoff scheduling
                $targetDate = $game->current_date?->toDateString() ?? now()->toDateString();
                $handler->beforeMatches($game, $targetDate);
            }
        }
    }
}
