<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\MatchAttendance;
use App\Models\Team;
use App\Models\TeamReputation;
use Illuminate\Support\Str;

/**
 * Resolves the per-fixture MatchAttendance record. Idempotent — calling
 * twice for the same match returns the existing row, which makes it safe
 * to invoke from the pre-match orchestrator hook, the live-match view
 * fallback, and the MatchFinalized safety-net listener.
 */
class MatchAttendanceService
{
    // Rounds that always sell out, regardless of venue. The first leg of a
    // two-legged semi-final is `cup.semi_finals`; the return leg is suffixed
    // `_return` in CupCompetitionHandler::createTie. Finals are single-leg.
    private const SOLD_OUT_ROUNDS = [
        'cup.final',
        'cup.semi_finals',
        'cup.semi_finals_return',
    ];

    public function __construct(
        private readonly DemandCurveService $demandCurve,
        private readonly SeasonTicketPricingService $seasonTicketPricingService,
        private readonly GameStadiumResolver $stadiumResolver,
    ) {}

    /** Per-request caches — MatchAttendanceService is resolved fresh per request. */
    private array $reputationCache = [];
    private array $clubProfileCache = [];
    private array $teamCache = [];

    /**
     * Return the MatchAttendance for this fixture, computing and persisting
     * it on first call. For matches at a designated neutral venue (cup
     * finals in career mode, World Cup fixtures without a venue override,
     * etc.) we record a sold-out house against the match's neutral
     * capacity instead of running the home club's demand curve.
     */
    public function resolveForMatch(GameMatch $match, Game $game): ?MatchAttendance
    {
        $existing = MatchAttendance::where('game_match_id', $match->id)->first();
        if ($existing) {
            return $existing;
        }

        $computed = $this->describeForMatch($match, $game);
        if ($computed === null) {
            return null;
        }

        return MatchAttendance::create([
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'attendance' => $computed['attendance'],
            'capacity_at_match' => $computed['capacity'],
        ]);
    }

    /**
     * Batch variant of resolveForMatch for an entire matchday batch.
     * Pre-warms the team and reputation caches in bulk, then writes all
     * missing MatchAttendance rows in a single insert. Idempotent: matches
     * that already have a row are skipped.
     *
     * @param  iterable<GameMatch>  $matches
     */
    public function resolveBatch(iterable $matches, Game $game): void
    {
        $matchIds = collect($matches)->pluck('id')->all();
        if (empty($matchIds)) {
            return;
        }

        $existingIds = MatchAttendance::whereIn('game_match_id', $matchIds)
            ->pluck('game_match_id')
            ->all();
        $existingIds = array_flip($existingIds);

        $remaining = collect($matches)->reject(fn ($m) => isset($existingIds[$m->id]));
        if ($remaining->isEmpty()) {
            return;
        }

        // Pre-warm team + reputation caches so describeForMatch's per-match
        // calls hit the in-memory map instead of issuing N queries.
        $teamIds = $remaining
            ->flatMap(fn ($m) => [$m->home_team_id, $m->away_team_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($teamIds)) {
            $missingTeamIds = array_filter(
                $teamIds,
                fn ($id) => ! array_key_exists($id, $this->teamCache),
            );
            if (! empty($missingTeamIds)) {
                foreach (Team::whereIn('id', $missingTeamIds)->get() as $team) {
                    $this->teamCache[$team->id] = $team;
                }
                // Cache nulls for any team IDs that didn't resolve so
                // loadTeam() doesn't issue follow-up queries for them.
                foreach ($missingTeamIds as $id) {
                    if (! array_key_exists($id, $this->teamCache)) {
                        $this->teamCache[$id] = null;
                    }
                }
            }

            $missingRepKeys = [];
            foreach ($teamIds as $tid) {
                $key = "{$game->id}|{$tid}";
                if (! array_key_exists($key, $this->reputationCache)) {
                    $missingRepKeys[$tid] = $key;
                }
            }
            if (! empty($missingRepKeys)) {
                $reps = TeamReputation::where('game_id', $game->id)
                    ->whereIn('team_id', array_keys($missingRepKeys))
                    ->get();
                foreach ($reps as $rep) {
                    $this->reputationCache["{$game->id}|{$rep->team_id}"] = $rep;
                }

                // Pre-load ClubProfile for teams that need a synthetic rep.
                $needsProfile = array_diff(
                    array_keys($missingRepKeys),
                    $reps->pluck('team_id')->all(),
                );
                $needsProfile = array_filter(
                    $needsProfile,
                    fn ($id) => ! array_key_exists($id, $this->clubProfileCache),
                );
                if (! empty($needsProfile)) {
                    foreach (ClubProfile::whereIn('team_id', $needsProfile)->get() as $profile) {
                        $this->clubProfileCache[$profile->team_id] = $profile;
                    }
                }
            }
        }

        $rows = [];
        foreach ($remaining as $match) {
            $computed = $this->describeForMatch($match, $game);
            if ($computed === null) {
                continue;
            }

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'game_match_id' => $match->id,
                'attendance' => $computed['attendance'],
                'capacity_at_match' => $computed['capacity'],
            ];
        }

        if (! empty($rows)) {
            MatchAttendance::insert($rows);
        }
    }

    /**
     * Compute the attendance figure for a fixture without persisting it.
     * Used by views that need to show the number before the orchestrator
     * has written the row (e.g. the pre-match modal, which renders before
     * MatchdayOrchestrator::processBatch runs). Covers the same branches
     * as resolveForMatch: sold-out rounds, explicit neutral venues, and
     * the general demand-curve projection.
     *
     * Differs from projectForMatch(), which intentionally returns null for
     * neutral venues because BudgetProjectionService doesn't need them.
     *
     * @return array{attendance: int, capacity: int}|null
     */
    public function describeForMatch(GameMatch $match, Game $game): ?array
    {
        if ($house = $this->soldOutHouse($match, $game)) {
            return $house;
        }

        if ($match->isNeutralVenue()) {
            // A designated neutral venue plays to its set capacity; without
            // one we have no home club to run the demand curve against.
            return $match->neutral_venue_capacity !== null
                ? $this->fullHouse((int) $match->neutral_venue_capacity)
                : null;
        }

        return $this->projectGeneral($match, $game);
    }

    /**
     * Compute the projected attendance for a fixture without persisting it.
     * Used by BudgetProjectionService to sum pre-season matchday revenue
     * across the upcoming schedule. Returns null for neutral-venue fixtures
     * and for matches whose home team can't be resolved.
     *
     * @return array{attendance: int, capacity: int}|null
     */
    public function projectForMatch(GameMatch $match, Game $game): ?array
    {
        if ($house = $this->soldOutHouse($match, $game)) {
            return $house;
        }

        if ($match->isNeutralVenue()) {
            return null;
        }

        return $this->projectGeneral($match, $game);
    }

    /**
     * General demand-curve projection for a normal home fixture (not a
     * sold-out round, not a neutral venue — those are resolved by the
     * callers first). Returns null when the home team can't be resolved.
     *
     * @return array{attendance: int, capacity: int}|null
     */
    private function projectGeneral(GameMatch $match, Game $game): ?array
    {
        $home = $this->loadTeam($match->home_team_id);
        if (!$home) {
            return null;
        }

        $competition = $match->competition ?? Competition::find($match->competition_id);

        $homeRep = $this->loadReputation($game->id, $home->id);
        $awayRep = $this->loadReputation($game->id, $match->away_team_id);

        $capacity = $this->stadiumResolver->effectiveCapacity(
            $game->id,
            $home->id,
            (int) ($home->stadium_seats ?? 0),
        );

        $attendance = $this->demandCurve->project(
            $home,
            $homeRep,
            $awayRep,
            $competition,
            $capacity,
        );

        $attendance = $this->composeSeasonTicketAttendance($match, $game, $attendance, $capacity);

        return [
            'attendance' => $attendance,
            'capacity' => $capacity,
        ];
    }

    /**
     * Recompose attendance for the user's home games around the season-ticket
     * base. A configurable share of holders are no-shows: they paid up front,
     * so an empty paid seat costs nothing and earns nothing. The rest attend,
     * and walk-up buyers fill the demand BEYOND the abono base. So the gate is
     * `attending holders + walk-ups`, not the raw demand-curve figure — which
     * lets a club that sold abonos to most of its crowd still report a few
     * empty seats, and a club with demand above its abono base still draw a
     * walk-up gate. A small deterministic per-match jitter on the walk-up keeps
     * consecutive fixtures from reading identically (stable across reloads, no
     * global PRNG touch). For non-user / away fixtures holders is 0 and the
     * demand-curve attendance passes through unchanged.
     *
     * The chosen preset's occupancy factor scales total demand first (cheaper
     * prices draw a bigger crowd, premium prices some out), so the walk-up that
     * fills in beyond the abono base — and the resulting occupancy — respond to
     * the pricing stance, not just the abono/walk-up split.
     *
     * `$attendance` is the demand-curve crowd (total match-going appetite).
     */
    private function composeSeasonTicketAttendance(GameMatch $match, Game $game, int $attendance, int $capacity): int
    {
        $holders = $this->seasonTicketPricingService->soldSeasonTicketsForMatch($game, $match);
        if ($holders <= 0 || $capacity <= 0) {
            return $attendance;
        }

        $demand = (int) round($attendance * $this->seasonTicketPricingService->currentOccupancyFactor($game));

        $noShowRate = (float) config('stadium.season_ticket_noshow_rate', 0.05);
        $attendingHolders = (int) round($holders * (1.0 - $noShowRate));

        // Walk-up = the demand beyond the abono base. Deterministic jitter
        // (−1% to +5% of capacity) derived from the match id so the same
        // fixture always reports the same number.
        $bucket = crc32($match->id) % 601; // 0..600 inclusive
        $jitterPercent = ($bucket - 100) / 10_000.0; // −0.01 to +0.05
        $jitterSeats = (int) round($capacity * $jitterPercent);
        $walkup = max(0, $demand - $holders + $jitterSeats);

        return min($capacity, $attendingHolders + $walkup);
    }

    /**
     * Season-average attendance for a team's home fixtures. Skips the
     * per-fixture queries that projectForMatch() performs — BudgetProjectionService
     * only needs an expected gate to multiply by the home-match count.
     */
    public function projectBaselineForTeam(string $gameId, Team $home): int
    {
        $homeRep = $this->loadReputation($gameId, $home->id);
        $capacity = $this->stadiumResolver->effectiveCapacity(
            $gameId,
            $home->id,
            (int) ($home->stadium_seats ?? 0),
        );

        return $this->demandCurve->projectBaseline($home, $homeRep, $capacity);
    }

    /**
     * The sold-out full house for a round that always sells out (cup
     * finals/semis), or null when this isn't such a round or its capacity
     * can't be resolved. The single source of truth for the sold-out rule,
     * shared by describeForMatch() and projectForMatch().
     *
     * @return array{attendance: int, capacity: int}|null
     */
    private function soldOutHouse(GameMatch $match, Game $game): ?array
    {
        if (! $this->isSoldOutRound($match)) {
            return null;
        }

        $capacity = $this->soldOutCapacity($match, $game);

        return $capacity > 0 ? $this->fullHouse($capacity) : null;
    }

    /**
     * A full-capacity house: attendance equals capacity.
     *
     * @return array{attendance: int, capacity: int}
     */
    private function fullHouse(int $capacity): array
    {
        return ['attendance' => $capacity, 'capacity' => $capacity];
    }

    private function isSoldOutRound(GameMatch $match): bool
    {
        return in_array($match->round_name, self::SOLD_OUT_ROUNDS, true);
    }

    /**
     * Capacity a final/semi-final plays to: the designated neutral venue
     * when one is set, otherwise the home club's own stadium.
     */
    private function soldOutCapacity(GameMatch $match, Game $game): int
    {
        if ($match->neutral_venue_capacity !== null) {
            return (int) $match->neutral_venue_capacity;
        }

        $home = $this->loadTeam($match->home_team_id);
        if (! $home) {
            return 0;
        }

        return $this->stadiumResolver->effectiveCapacity(
            $game->id,
            $home->id,
            (int) ($home->stadium_seats ?? 0),
        );
    }

    private function loadTeam(string $teamId): ?Team
    {
        if (! array_key_exists($teamId, $this->teamCache)) {
            $this->teamCache[$teamId] = Team::find($teamId);
        }

        return $this->teamCache[$teamId];
    }

    /**
     * Load the game-scoped TeamReputation row, falling back to a synthetic
     * instance seeded from ClubProfile when no row exists (e.g. teams from
     * outside the primary game that occasionally appear in cups).
     */
    private function loadReputation(string $gameId, string $teamId): TeamReputation
    {
        $key = "$gameId|$teamId";
        if (array_key_exists($key, $this->reputationCache)) {
            return $this->reputationCache[$key];
        }

        $rep = TeamReputation::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->first();

        if ($rep) {
            return $this->reputationCache[$key] = $rep;
        }

        if (! array_key_exists($teamId, $this->clubProfileCache)) {
            $this->clubProfileCache[$teamId] = ClubProfile::where('team_id', $teamId)->first();
        }
        $profile = $this->clubProfileCache[$teamId];
        $level = $profile->reputation_level ?? ClubProfile::REPUTATION_LOCAL;
        $anchor = (int) ($profile->fan_loyalty ?? ClubProfile::FAN_LOYALTY_DEFAULT);
        $loyalty = $anchor * 10;

        $synthetic = new TeamReputation();
        $synthetic->game_id = $gameId;
        $synthetic->team_id = $teamId;
        $synthetic->reputation_level = $level;
        $synthetic->base_reputation_level = $level;
        $synthetic->reputation_points = TeamReputation::pointsForTier($level);
        $synthetic->base_loyalty = $loyalty;
        $synthetic->loyalty_points = $loyalty;

        return $this->reputationCache[$key] = $synthetic;
    }
}
