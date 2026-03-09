<?php

namespace App\Modules\Match\Handlers;

use App\Modules\Competition\Contracts\CompetitionHandler;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class PreSeasonHandler implements CompetitionHandler
{
    public function getType(): string
    {
        return 'preseason';
    }

    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $gameId)
            ->where('competition_id', $nextMatch->competition_id)
            ->whereDate('scheduled_date', $nextMatch->scheduled_date)
            ->where('played', false)
            ->get();
    }

    public function beforeMatches(Game $game, string $targetDate): void
    {
        // No pre-match actions for pre-season matches
    }

    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        // No post-match actions — no standings, no prize money, no cup ties
    }

    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string
    {
        return route('game.results', [
            'gameId' => $game->id,
            'competition' => $matches->first()->competition_id ?? $game->competition_id,
            'matchday' => $matchday,
        ]);
    }
}
