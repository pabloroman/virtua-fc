<?php

namespace App\Modules\Match\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the strength FLOOR used to rescale team strengths for a match
 * (see {@see \App\Modules\Match\Support\MatchOutcomeModel::applyFloor()}).
 *
 * Why this exists: team strength is `mean(rating)/100` on a zero-baseline 0..100
 * scale, but no real squad rates below ~50, so the bottom half is dead weight
 * that squashes every strength ratio toward 1.0 and makes matches coin flips.
 * Subtracting a floor re-expands the ratios. The catch is that the right floor
 * is league-specific: a league whose ratings sit in a high, narrow band (the
 * cash-rich, egalitarian Premier League: 76.7→88) needs a much larger floor
 * than one with a low, wide band (La Liga: 72.5→91.7). So the floor is DERIVED
 * from each competition's own rating distribution rather than hard-coded.
 *
 * One global knob drives it: `strength_ratio_target` (R). For a league with
 * rating band [bottom, top], the floor that makes the strongest/weakest strength
 * ratio equal R is `F = (R·bottom − top)/(R − 1)`. The same R yields ≈16 for
 * La Liga and ≈44 for the Premier League — matching what the realism diagnostic
 * found by hand.
 *
 * Match classification:
 *  - DOMESTIC LEAGUE match → that league's own derived floor.
 *  - Everything else (domestic cups, continental, World Cup, pre-season) →
 *    a GLOBAL floor pooled across all domestic leagues in the game. That band
 *    is very wide, so the global floor collapses toward 0 — i.e. cross-band
 *    fixtures fall back to the raw, globally-consistent overalls, preserving the
 *    genuine quality gap (a richer league's team really is stronger in Europe).
 *
 * Floors are memoized per (game, competition) for the lifetime of the instance,
 * so resolving every fixture in a matchday batch costs at most one windowed
 * query per league.
 */
class CompetitionStrengthFloorResolver
{
    private const MIN_TEAMS = 6;

    /** @var array<string, float> "gameId|competitionId" => resolved match floor */
    private array $matchFloorCache = [];

    /** @var array<string, float> "gameId|competitionId" => domestic-league floor */
    private array $leagueFloorCache = [];

    /** @var array<string, float> "gameId" => global floor */
    private array $globalFloorCache = [];

    /** @var array<string, bool> competitionId => is domestic league */
    private array $domesticLeagueCache = [];

    /**
     * The strength floor for a specific match, chosen by its competition type.
     * Returns 0.0 (an exact no-op in applyFloor) when the feature is disabled.
     */
    public function floorForMatch(Game $game, GameMatch $match): float
    {
        if (! config('match_simulation.strength_floor_enabled', true)) {
            return 0.0;
        }

        $competitionId = $match->competition_id;
        $key = $game->id . '|' . $competitionId;

        if (array_key_exists($key, $this->matchFloorCache)) {
            return $this->matchFloorCache[$key];
        }

        $floor = $this->isDomesticLeague($competitionId)
            ? $this->leagueFloor($game, $competitionId)
            : $this->globalFloor($game);

        return $this->matchFloorCache[$key] = $floor;
    }

    /**
     * Floor derived from a single domestic league's own rating band.
     */
    public function leagueFloor(Game $game, string $competitionId): float
    {
        $key = $game->id . '|' . $competitionId;
        if (array_key_exists($key, $this->leagueFloorCache)) {
            return $this->leagueFloorCache[$key];
        }

        $ratings = $this->teamRatings($game->id, [$competitionId]);

        return $this->leagueFloorCache[$key] = $this->deriveFloor($ratings);
    }

    /**
     * Floor derived from the pooled rating band of every domestic league in the
     * game — the cross-band reference for cups and continental competitions.
     */
    public function globalFloor(Game $game): float
    {
        if (array_key_exists($game->id, $this->globalFloorCache)) {
            return $this->globalFloorCache[$game->id];
        }

        $leagueIds = $this->domesticLeagueIds($game->id);
        $ratings = $leagueIds === [] ? [] : $this->teamRatings($game->id, $leagueIds);

        return $this->globalFloorCache[$game->id] = $this->deriveFloor($ratings);
    }

    /**
     * Solve for the floor that makes the band's top:bottom strength ratio equal
     * the configured target R, then clamp it strictly below the weakest team so
     * every squad keeps a positive rescaled strength. A degenerate band (too few
     * teams, or a broken low outlier that drives the floor negative) returns 0.0,
     * gracefully disabling the rescale rather than producing a wild floor.
     *
     * @param  array<int, float>  $ratings  per-team mean top-N overall_score
     */
    private function deriveFloor(array $ratings): float
    {
        if (count($ratings) < self::MIN_TEAMS) {
            return 0.0;
        }

        $bottom = min($ratings);
        $top = max($ratings);

        $target = (float) config('match_simulation.strength_ratio_target', 1.34);
        $margin = (float) config('match_simulation.strength_floor_margin', 3.0);

        if ($target <= 1.0 || $top <= $bottom) {
            return 0.0;
        }

        $floor = ($target * $bottom - $top) / ($target - 1.0);
        $floor = min($floor, $bottom - $margin);

        return max(0.0, $floor);
    }

    /**
     * Per-team mean of the top-N `overall_score` for every team entered in the
     * given competition(s) of this game. Reuses the windowed-query pattern from
     * SyntheticLeagueResolver::loadTeamStrengths, scoped via competition_entries.
     *
     * @param  array<int, string>  $competitionIds
     * @return array<int, float>  one mean rating per team
     */
    private function teamRatings(string $gameId, array $competitionIds): array
    {
        if ($competitionIds === []) {
            return [];
        }

        $topN = (int) config('match_simulation.strength_floor_top_n', 11);
        $placeholders = implode(',', array_fill(0, count($competitionIds), '?'));
        $bindings = array_merge([$gameId], $competitionIds, [$gameId, $topN]);

        $rows = DB::select(<<<SQL
            SELECT AVG(overall_score)::float AS rating
            FROM (
                SELECT
                    gp.team_id,
                    gp.overall_score,
                    ROW_NUMBER() OVER (PARTITION BY gp.team_id ORDER BY gp.overall_score DESC) AS rn
                FROM game_players gp
                WHERE gp.game_id = ?
                  AND gp.team_id IN (
                      SELECT team_id FROM competition_entries
                      WHERE competition_id IN ({$placeholders}) AND game_id = ?
                  )
                  AND gp.overall_score IS NOT NULL
            ) ranked
            WHERE rn <= ?
            GROUP BY team_id
        SQL, $bindings);

        return array_map(static fn ($row) => (float) $row->rating, $rows);
    }

    private function isDomesticLeague(string $competitionId): bool
    {
        if (array_key_exists($competitionId, $this->domesticLeagueCache)) {
            return $this->domesticLeagueCache[$competitionId];
        }

        $competition = Competition::find($competitionId);

        return $this->domesticLeagueCache[$competitionId] = (bool) $competition?->isDomesticLeague();
    }

    /**
     * Codes of every domestic league that has entries in this game.
     *
     * @return array<int, string>
     */
    private function domesticLeagueIds(string $gameId): array
    {
        return Competition::query()
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->where('role', Competition::ROLE_LEAGUE)
            ->where('scope', Competition::SCOPE_DOMESTIC)
            ->whereIn('id', function ($query) use ($gameId) {
                $query->select('competition_id')
                    ->from('competition_entries')
                    ->where('game_id', $gameId);
            })
            ->pluck('id')
            ->all();
    }
}
