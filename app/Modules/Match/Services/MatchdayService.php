<?php

namespace App\Modules\Match\Services;

use App\Modules\Match\Handlers\KnockoutCupHandler;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;
use App\Modules\Competition\Services\CompetitionHandlerResolver;

class MatchdayService
{
    public function __construct(
        private readonly CompetitionHandlerResolver $handlerResolver,
        private readonly KnockoutCupHandler $cupHandler,
    ) {}

    /**
     * Get the next batch of matches to play across all competitions on the same date.
     *
     * @return array{matches: Collection, handlers: array, matchday: int, currentDate: string}|null
     */
    public function getNextMatchBatch(Game $game): ?array
    {
        // Generate any pending knockout/playoff matches for hybrid competitions
        $this->generatePendingMatches($game);

        $nextMatch = $this->findNextMatch($game->id);

        if (!$nextMatch) {
            return null;
        }

        $targetDate = $nextMatch->scheduled_date->toDateString();

        // Conduct any pending cup draws (may create new matches)
        $this->cupHandler->beforeMatches($game, $targetDate);

        // Re-fetch in case cup matches were created
        $nextMatch = $this->findNextMatch($game->id);

        if (!$nextMatch) {
            return null;
        }

        $targetDate = $nextMatch->scheduled_date->toDateString();

        // Get ALL unplayed matches on this date across all competitions
        $matchesOnDate = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->whereDate('scheduled_date', $targetDate)
            ->get();

        // Resolve handlers and call beforeMatches for each competition
        $competitionIds = $matchesOnDate->pluck('competition_id')->unique();
        $competitions = Competition::whereIn('id', $competitionIds)->get()->keyBy('id');

        $handlers = [];
        foreach ($matchesOnDate->groupBy('competition_id') as $competitionId => $compMatches) {
            $competition = $competitions->get($competitionId);
            $handler = $this->handlerResolver->resolve($competition);
            $handler->beforeMatches($game, $targetDate);
            $handlers[$competitionId] = $handler;
        }

        // For league matches, expand to include the full round (may span dates)
        $allMatches = $matchesOnDate;
        $leagueMatches = $matchesOnDate->whereNull('cup_tie_id');

        foreach ($leagueMatches->groupBy('competition_id') as $competitionId => $compMatches) {
            $roundNumber = $compMatches->first()->round_number;
            $fullRound = GameMatch::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->where('round_number', $roundNumber)
                ->whereNull('cup_tie_id')
                ->where('played', false)
                ->get();
            $allMatches = $allMatches->merge($fullRound);
        }

        $allMatches = $allMatches->unique('id')->values();
        $allMatches->load(['competition', 'homeTeam', 'awayTeam']);

        if ($allMatches->isEmpty()) {
            return null;
        }

        // Determine matchday number (prefer league round if available)
        $leagueMatch = $allMatches->first(fn ($m) => $m->competition->isLeague() && !$m->cup_tie_id);
        $matchday = $leagueMatch->round_number ?? $game->current_matchday;

        // For matchdays spanning multiple days, use the latest date
        $currentDate = $allMatches->max('scheduled_date')->toDateString();

        return [
            'matches' => $allMatches,
            'handlers' => $handlers,
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
