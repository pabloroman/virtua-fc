<?php

namespace App\Game\Services;

use App\Game\Handlers\KnockoutCupHandler;
use App\Game\Handlers\LeagueWithPlayoffHandler;
use App\Game\Handlers\SwissFormatHandler;
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
        // Generate any pending knockout/playoff matches for hybrid competitions
        $this->generatePendingMatches($game);

        $nextMatch = $this->findNextMatch($game->id);

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
        $isLeagueMatch = $matchCompetition?->isLeague();
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
     * Generate any pending knockout/playoff matches for hybrid competitions.
     * Checks both league_with_playoff and swiss_format competitions.
     */
    private function generatePendingMatches(Game $game): void
    {
        $competitions = Competition::whereIn('handler_type', ['league_with_playoff', 'swiss_format'])->get();

        $targetDate = $game->current_date?->toDateString() ?? now()->toDateString();

        foreach ($competitions as $competition) {
            $hasMatches = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->exists();

            if (!$hasMatches) {
                continue;
            }

            $handler = $this->handlerResolver->resolve($competition);
            $handler->beforeMatches($game, $targetDate);
        }
    }
}
