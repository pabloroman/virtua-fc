<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use Carbon\Carbon;

/**
 * Resolves the "next match" context for a game in one place: the game with
 * its eager-loaded relations, the upcoming match (already loaded with the
 * teams + competition the views render), and the user-side derivations
 * (isHome, opponent, matchDate, competitionId).
 *
 * Pre-match views (ShowLineup, ShowOpponentAnalysis) all start from the same
 * 8-line preamble; this collapses it into a single call so they stay focused
 * on the work that's actually different.
 */
class PreMatchContext
{
    public function __construct(
        public readonly Game $game,
        public readonly GameMatch $match,
        public readonly bool $isHome,
        public readonly Team $opponent,
        public readonly Carbon $matchDate,
        public readonly string $competitionId,
    ) {}

    /**
     * Load the game (with the supplied eager-load list), pull the next
     * unplayed match, eager-load its teams + competition, and derive the
     * user-side facts. Aborts 404 when there is no next match.
     *
     * @param  list<string>  $with  extra Game relations to eager-load
     */
    public static function resolve(string $gameId, array $with = ['team']): self
    {
        $game = Game::with($with)->findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        $match->load(['homeTeam', 'awayTeam', 'competition']);

        $isHome = $match->home_team_id === $game->team_id;
        $opponent = $isHome ? $match->awayTeam : $match->homeTeam;

        return new self(
            game: $game,
            match: $match,
            isHome: $isHome,
            opponent: $opponent,
            matchDate: $match->scheduled_date,
            competitionId: $match->competition_id,
        );
    }
}
