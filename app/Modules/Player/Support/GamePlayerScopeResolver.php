<?php

namespace App\Modules\Player\Support;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;

/**
 * Single source of truth for the "active" team / player scope.
 *
 * A player is considered active (and therefore needs a
 * {@see \App\Models\GamePlayerMatchState} satellite row) iff their team
 * belongs to a competition in the game's country — i.e. the user's
 * domestic pyramid (La Liga, Segunda, Copa del Rey for an ES game).
 *
 * Foreign-league teams (ENG1, DEU1, FRA1, ITA1, EUR pool) are out of scope:
 * their players exist purely to populate the international transfer market
 * and never participate in simulated matches. They get folded back into
 * scope on demand by {@see \App\Modules\Season\Processors\ContinentalAndCupInitProcessor}
 * when the user qualifies for a European competition that draws them.
 *
 * Mirrors the classification used by
 * {@see \App\Modules\Transfer\Services\ScoutSearchQueryBuilder::applyScopeFilter()}.
 */
class GamePlayerScopeResolver
{
    /** @var array<string, string[]> Cached active team ids keyed by game id */
    private array $cache = [];

    /**
     * Return the set of team ids that are "active" for the given game.
     *
     * @return string[]
     */
    public function activeTeamIdsForGame(Game $game): array
    {
        if (isset($this->cache[$game->id])) {
            return $this->cache[$game->id];
        }

        return $this->cache[$game->id] = $this->resolve($game->country);
    }

    /**
     * Variant that takes a country code directly. Useful at game-creation
     * time before the Game model is fully hydrated.
     *
     * @return string[]
     */
    public function activeTeamIdsForCountry(string $country): array
    {
        return $this->resolve($country);
    }

    /**
     * Forget any cached lookups for a game (e.g. after a transfer changes
     * the active team set).
     */
    public function forget(string $gameId): void
    {
        unset($this->cache[$gameId]);
    }

    /**
     * @return string[]
     */
    private function resolve(string $country): array
    {
        $competitionIds = Competition::where('country', $country)->pluck('id');

        return Team::transferMarketEligible()
            ->whereHas('competitions', fn ($q) => $q->whereIn('competitions.id', $competitionIds))
            ->pluck('id')
            ->all();
    }
}
