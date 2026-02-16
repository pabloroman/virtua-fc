<?php

namespace App\Game\Handlers;

use App\Game\Contracts\CompetitionHandler;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class LeagueHandler implements CompetitionHandler
{
    public function getType(): string
    {
        return 'league';
    }

    /**
     * Get all unplayed league matches from the same matchday (round_number).
     * League matches are grouped by matchday regardless of their individual dates.
     */
    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $gameId)
            ->where('competition_id', $nextMatch->competition_id)
            ->where('round_number', $nextMatch->round_number)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->get();
    }

    /**
     * Leagues don't have pre-match actions.
     */
    public function beforeMatches(Game $game, string $targetDate): void
    {
        // No pre-match actions needed for leagues
    }

    /**
     * Leagues don't have post-match actions.
     * Standings are updated by the GameProjector when match results are recorded.
     */
    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        // No post-match actions needed for leagues
        // Standings are updated automatically by GameProjector
    }

    /**
     * Redirect to the match results page for this matchday.
     */
    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string
    {
        return route('game.results', [
            'gameId' => $game->id,
            'competition' => $matches->first()->competition_id ?? $game->competition_id,
            'matchday' => $matchday,
        ]);
    }
}
