<?php

namespace App\Game\Contracts;

use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

interface CompetitionHandler
{
    /**
     * Get the competition handler type this handler supports.
     * e.g., 'league', 'knockout_cup', 'group_stage_cup'
     */
    public function getType(): string;

    /**
     * Given the next scheduled match, return all matches that should
     * be played together in this advancement.
     *
     * @return Collection<GameMatch>
     */
    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection;

    /**
     * Actions to perform before matches are simulated.
     * e.g., conducting cup draws
     */
    public function beforeMatches(Game $game, string $targetDate): void;

    /**
     * Actions to perform after match results are recorded.
     * e.g., resolving cup ties, updating group standings
     *
     * @param Collection<GameMatch> $matches The matches that were played
     * @param Collection $allPlayers All game players grouped by team_id
     */
    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void;

    /**
     * Determine the redirect route after advancement.
     *
     * @param Collection<GameMatch> $matches The matches that were played
     */
    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string;
}
