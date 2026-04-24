<?php

namespace App\Modules\Competition\Services;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;

/**
 * Resolves promotion-playoff ties that remain level after extra time by
 * awarding the win to the team that finished higher in its regular-season
 * table. Applies to Spain's La Liga 2 → La Liga playoff and the Primera
 * Federación → La Liga 2 bracket; other ties (domestic cups, UEFA) fall
 * back to penalties.
 */
class PlayoffTiebreakerService
{
    public function __construct(
        private readonly CountryConfig $countryConfig,
    ) {}

    /**
     * Whether this tie uses the higher-regular-season-finisher rule instead
     * of penalties when level after extra time.
     */
    public function appliesTo(CupTie $tie): bool
    {
        return !empty($this->countryConfig->playoffTiebreakerSources($tie->competition_id));
    }

    /**
     * Resolve a level tie by regular-season finishing position. Returns the
     * higher-finishing team's ID, or null if the positions can't be
     * established (falls back to penalties in that edge case).
     */
    public function resolveWinner(CupTie $tie, Game $game): ?string
    {
        $sources = $this->countryConfig->playoffTiebreakerSources($tie->competition_id);
        if (empty($sources)) {
            return null;
        }

        $homePos = $this->regularSeasonPosition($game, $tie->home_team_id, $sources);
        $awayPos = $this->regularSeasonPosition($game, $tie->away_team_id, $sources);

        if ($homePos === null || $awayPos === null || $homePos === $awayPos) {
            return null;
        }

        return $homePos < $awayPos ? $tie->home_team_id : $tie->away_team_id;
    }

    /**
     * Look up a team's regular-season finishing position in its source
     * league. Prefers real GameStanding rows; falls back to SimulatedSeason
     * so sister-group standings (e.g. the group the player isn't in under
     * Primera RFEF's split format) are still usable.
     *
     * @param string[] $sourceCompetitionIds
     */
    private function regularSeasonPosition(Game $game, string $teamId, array $sourceCompetitionIds): ?int
    {
        foreach ($sourceCompetitionIds as $sourceId) {
            $standing = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $sourceId)
                ->where('team_id', $teamId)
                ->first();

            if ($standing) {
                return $standing->position;
            }
        }

        foreach ($sourceCompetitionIds as $sourceId) {
            $sim = SimulatedSeason::where('game_id', $game->id)
                ->where('competition_id', $sourceId)
                ->where('season', $game->season)
                ->first();

            if (!$sim || empty($sim->results)) {
                continue;
            }

            $index = array_search($teamId, $sim->results, true);
            if ($index !== false) {
                return $index + 1;
            }
        }

        return null;
    }
}
