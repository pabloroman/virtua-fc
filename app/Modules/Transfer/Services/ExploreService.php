<?php

namespace App\Modules\Transfer\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Support\CountryCodeMapper;
use App\Support\PositionMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExploreService
{
    private const POSITION_GROUP_ORDER = [
        'Goalkeeper' => 0,
        'Defender' => 1,
        'Midfielder' => 2,
        'Forward' => 3,
    ];

    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    /**
     * Get domestic league competitions with team counts for a game.
     */
    public function getCompetitionsWithTeamCounts(string $gameId): Collection
    {
        $competitionIds = CompetitionEntry::where('game_id', $gameId)
            ->distinct()
            ->pluck('competition_id');

        $teamCounts = CompetitionEntry::where('game_id', $gameId)
            ->whereIn('competition_id', $competitionIds)
            ->selectRaw('competition_id, count(*) as team_count')
            ->groupBy('competition_id')
            ->pluck('team_count', 'competition_id');

        return Competition::whereIn('id', $competitionIds)
            ->where('role', Competition::ROLE_LEAGUE)
            ->where('scope', Competition::SCOPE_DOMESTIC)
            ->orderBy('country')
            ->get()
            ->map(fn (Competition $comp) => [
                'id' => $comp->id,
                'name' => __($comp->name),
                'country' => $comp->country,
                'flag' => $comp->flag,
                'tier' => $comp->tier,
                'scope' => $comp->scope,
                'teamCount' => $teamCounts->get($comp->id, 0),
            ])
            ->filter(fn ($c) => $c['teamCount'] > 0)
            ->values();
    }

    /**
     * Get teams for a competition in a game, sorted by name.
     */
    public function getTeamsForCompetition(string $gameId, string $competitionId): Collection
    {
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id');

        return Team::whereIn('id', $teamIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'image' => $team->image,
            ]);
    }

    /**
     * Get teams from a transfer-only team pool (e.g. EUR, INT) grouped by
     * country. Used by the Explore dropdown to render "Europe" and
     * "International" scopes with the same two-column UI.
     *
     * @return Collection<int, array{code: string, name: string, flag: string, teams: array}>
     */
    public function getTeamPoolGroupedByCountry(string $gameId, string $competitionId): Collection
    {
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id');

        $teams = Team::whereIn('id', $teamIds)
            ->orderBy('name')
            ->get();

        return $teams
            ->groupBy('country')
            ->map(function (Collection $groupTeams, string $countryCode) {
                $code = strtolower($countryCode);
                $englishName = CountryCodeMapper::toName($countryCode) ?? $countryCode;
                $translatedName = __("countries.{$englishName}");

                return [
                    'code' => $code,
                    'name' => $translatedName,
                    'flag' => $code,
                    'teams' => $groupTeams->map(fn (Team $team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'image' => $team->image,
                    ])->values()->all(),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * Count teams in a transfer-only team pool for a game.
     */
    public function getTeamPoolCount(string $gameId, string $competitionId): int
    {
        return CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->count();
    }

    /**
     * Get a team's squad for the explore view, with loan and shortlist status.
     */
    public function getSquadForTeam(Game $game, string $teamId): Collection
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->with(['player', 'team'])
            ->get();

        $playerIds = $players->pluck('id')->toArray();

        $activeLoans = Loan::where('game_id', $game->id)
            ->whereIn('game_player_id', $playerIds)
            ->active()
            ->with(['parentTeam', 'loanTeam'])
            ->get()
            ->keyBy('game_player_id');

        $shortlistedIds = $this->getShortlistedIds($game->id, $playerIds);

        return $players->map(function ($gp) use ($activeLoans, $shortlistedIds, $teamId) {
            $loan = $activeLoans->get($gp->id);
            $gp->is_loaned_in = $loan && $loan->loan_team_id === $teamId;
            $gp->is_shortlisted = in_array($gp->id, $shortlistedIds);

            return $gp;
        })->sort(fn ($a, $b) => $this->sortByPositionThenValue($a, $b));
    }

    /**
     * Get free agents for a game, optionally filtered by position group.
     * Each player is annotated with shortlist status and willingness level.
     */
    public function getFreeAgents(Game $game, string $positionFilter = 'all'): Collection
    {
        $query = GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->with('player');

        $positions = PositionMapper::getPositionsForGroupFilter($positionFilter);
        if ($positions !== null) {
            $query->whereIn('position', $positions);
        }

        $players = $query->get();

        $shortlistedIds = $this->getShortlistedIds($game->id, $players->pluck('id')->toArray());

        return $players->map(function ($gp) use ($shortlistedIds, $game) {
            $gp->is_shortlisted = in_array($gp->id, $shortlistedIds);
            $gp->free_agent_willingness = $this->scoutingService->getFreeAgentWillingnessLevel(
                $gp, $game->id, $game->team_id
            );

            return $gp;
        })->sortByDesc('market_value_cents')->values();
    }

    /** Hard ceiling on how many rows Explore returns in one response. */
    public const ADVANCED_SEARCH_LIMIT = 100;

    /**
     * Advanced player search across the full game database.
     *
     * Exposes only publicly observable data filters (name, position, age,
     * nationality, league, team, market value, contract year). Ability,
     * wage, and willingness intentionally stay behind scouting — exposing them
     * here would erode the value of the scouting tier.
     *
     * Returns a fixed-size window plus a `total` count so the UI can surface
     * a "refine to see more" hint when the result set is truncated.
     *
     * @param array{
     *     name?: string,
     *     position?: string,      // Group filter key: gk|def|mid|fwd
     *     min_age?: int,
     *     max_age?: int,
     *     nationality?: string,   // Country name as stored in players.nationality JSON
     *     competition_id?: string,
     *     team_id?: string,       // 'free_agents' for no team
     *     min_value?: int,        // euros
     *     max_value?: int,        // euros
     *     max_contract_year?: int,
     *     min_overall?: int,      // 0-99, average of technical + physical
     *     max_overall?: int,
     * } $filters
     * @return array{players: Collection<int, GamePlayer>, total: int, truncated: bool}
     */
    public function advancedSearch(Game $game, array $filters): array
    {
        $query = GamePlayer::where('game_id', $game->id)
            ->with(['player', 'team']);

        // PLANES-SEAM: name/age/nationality cross-plane filters.
        // game_players=tenant, players=control. These were folded into a
        // single Player::pluck → whereIn split, but for wide ranges that
        // shipped a list bounded only by the global player pool — the same
        // OOM trap the ability filter hit. Restored to the pre-split
        // whereHas / correlated subquery form while both planes share one
        // physical Postgres. Re-split before the planes are physically
        // separated. See CLAUDE.md → "Control plane / tenant plane".
        if (!empty($filters['name']) && mb_strlen($filters['name']) >= 2) {
            $needle = mb_strtolower($filters['name']);
            $query->whereHas('player', function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $needle . '%']);
            });
        }

        if (!empty($filters['position'])) {
            // Accepts both group keys (gk|def|mid|fwd) and scout-style filter
            // codes (GK, CB, any_defender, …). Groups map to their canonical
            // positions; specific codes resolve to a single position.
            $positions = PositionMapper::getPositionsForGroupFilter($filters['position'])
                ?? PositionMapper::getPositionsForFilter($filters['position']);
            if ($positions !== null && $positions !== []) {
                // Match primary position OR any entry in the secondary_positions
                // JSON array, so e.g. a CB/DM shows up under "Midfielders".
                $placeholders = implode(',', array_fill(0, count($positions), '?'));
                $query->where(function ($inner) use ($positions, $placeholders) {
                    $inner->whereIn('position', $positions)
                        ->orWhereRaw(
                            "(secondary_positions IS NOT NULL AND jsonb_exists_any(secondary_positions::jsonb, ARRAY[$placeholders]::text[]))",
                            $positions
                        );
                });
            }
        }

        if (!empty($filters['min_age']) || !empty($filters['max_age'])) {
            $gameDate = $game->current_date->toDateString();
            $ageExpr = 'EXTRACT(YEAR FROM AGE(?::date, (SELECT date_of_birth FROM players WHERE players.id = game_players.player_id)))';
            if (!empty($filters['min_age'])) {
                $query->whereRaw("($ageExpr) >= ?", [$gameDate, (int) $filters['min_age']]);
            }
            if (!empty($filters['max_age'])) {
                $query->whereRaw("($ageExpr) <= ?", [$gameDate, (int) $filters['max_age']]);
            }
        }

        if (!empty($filters['nationality'])) {
            // players.nationality is stored as a JSON array of country names
            // (["France", "Spain"]). ?::jsonb matches if the array contains the value.
            $query->whereHas('player', function ($q) use ($filters) {
                $q->whereRaw('nationality::jsonb @> ?::jsonb', [json_encode([$filters['nationality']])]);
            });
        }

        if (!empty($filters['competition_id'])) {
            $teamIds = CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $filters['competition_id'])
                ->pluck('team_id');
            $query->whereIn('team_id', $teamIds);
        }

        if (!empty($filters['team_id'])) {
            if ($filters['team_id'] === 'free_agents') {
                $query->whereNull('team_id');
            } else {
                $query->where('team_id', $filters['team_id']);
            }
        }

        if (!empty($filters['min_value'])) {
            $query->where('market_value_cents', '>=', (int) $filters['min_value'] * 100);
        }
        if (!empty($filters['max_value'])) {
            $query->where('market_value_cents', '<=', (int) $filters['max_value'] * 100);
        }

        if (!empty($filters['max_contract_year'])) {
            // Players whose contract ends on or before Dec 31 of the given year.
            $query->where(function ($q) use ($filters) {
                $q->whereNull('contract_until')
                    ->orWhereYear('contract_until', '<=', (int) $filters['max_contract_year']);
            });
        }

        if (!empty($filters['min_overall']) || !empty($filters['max_overall'])) {
            // Use the stable overall_score baseline so filter results stay
            // consistent across matchdays instead of shifting with daily form.
            //
            // PLANES-SEAM: cross-plane correlated subquery. game_players=tenant,
            // players=control. The two-step split version (pluck globally
            // qualifying player_ids, then OR + whereIn) OOMed PHP when the
            // ability range was wide. Restored while both planes share one
            // physical Postgres. Re-split before the planes are physically
            // separated. See CLAUDE.md → "Control plane / tenant plane".
            $overallExpr = 'COALESCE(game_players.overall_score, (SELECT overall_score FROM players WHERE players.id = game_players.player_id))';
            if (!empty($filters['min_overall'])) {
                $query->whereRaw("$overallExpr >= ?", [(int) $filters['min_overall']]);
            }
            if (!empty($filters['max_overall'])) {
                $query->whereRaw("$overallExpr <= ?", [(int) $filters['max_overall']]);
            }
        }

        $total = (clone $query)->count();

        $players = $query->limit(self::ADVANCED_SEARCH_LIMIT)->get();

        $shortlistedIds = $this->getShortlistedIds($game->id, $players->pluck('id')->toArray());

        $players = $players
            ->map(function ($gp) use ($shortlistedIds) {
                $gp->is_shortlisted = in_array($gp->id, $shortlistedIds);

                return $gp;
            })
            ->sort(fn ($a, $b) => $this->sortByPositionThenValue($a, $b))
            ->values();

        return [
            'players' => $players,
            'total' => $total,
            'truncated' => $total > self::ADVANCED_SEARCH_LIMIT,
        ];
    }

    /**
     * True when any advanced-search filter is set (beyond just a name).
     */
    public static function hasAdvancedFilters(array $filters): bool
    {
        foreach (['position', 'min_age', 'max_age', 'nationality', 'competition_id', 'team_id', 'min_value', 'max_value', 'max_contract_year', 'min_overall', 'max_overall'] as $key) {
            if (!empty($filters[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Distinct primary nationalities present among players in this game,
     * sorted alphabetically. Used to populate the nationality dropdown so the
     * list never contains options that would return zero results.
     *
     * @return array<int, string>
     */
    public function getDistinctNationalities(string $gameId): array
    {
        $rows = DB::table('game_players')
            ->where('game_id', $gameId)
            ->whereRaw("jsonb_typeof(nationality::jsonb) = 'array'")
            ->selectRaw("DISTINCT nationality::jsonb->>0 AS nat")
            ->pluck('nat')
            ->filter()
            ->unique()
            ->values()
            ->all();

        sort($rows, SORT_NATURAL | SORT_FLAG_CASE);

        return $rows;
    }

    /**
     * Count free agents in a game.
     */
    public function getFreeAgentCount(string $gameId): int
    {
        return GamePlayer::where('game_id', $gameId)
            ->whereNull('team_id')
            ->count();
    }

    /**
     * Get shortlisted player IDs from a set of player IDs.
     *
     * @return array<string>
     */
    private function getShortlistedIds(string $gameId, array $playerIds): array
    {
        return ShortlistedPlayer::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->pluck('game_player_id')
            ->toArray();
    }

    /**
     * Sort comparator: position group order, then market value descending.
     */
    private function sortByPositionThenValue(GamePlayer $a, GamePlayer $b): int
    {
        $groupA = self::POSITION_GROUP_ORDER[PositionMapper::getPositionGroup($a->position)] ?? 2;
        $groupB = self::POSITION_GROUP_ORDER[PositionMapper::getPositionGroup($b->position)] ?? 2;

        return $groupA <=> $groupB ?: $b->market_value_cents <=> $a->market_value_cents;
    }
}
