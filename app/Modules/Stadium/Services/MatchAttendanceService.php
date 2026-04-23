<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\MatchAttendance;
use App\Models\Team;
use App\Models\TeamReputation;

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
    ) {}

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

        if ($this->isSoldOutRound($match)) {
            $capacity = $this->soldOutCapacity($match);
            if ($capacity > 0) {
                return MatchAttendance::create([
                    'game_id' => $game->id,
                    'game_match_id' => $match->id,
                    'attendance' => $capacity,
                    'capacity_at_match' => $capacity,
                ]);
            }
        }

        if ($match->isNeutralVenue() && $match->neutral_venue_capacity !== null) {
            $capacity = (int) $match->neutral_venue_capacity;
            return MatchAttendance::create([
                'game_id' => $game->id,
                'game_match_id' => $match->id,
                'attendance' => $capacity,
                'capacity_at_match' => $capacity,
            ]);
        }

        $projection = $this->projectForMatch($match, $game);
        if ($projection === null) {
            return null;
        }

        return MatchAttendance::create([
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'attendance' => $projection['attendance'],
            'capacity_at_match' => $projection['capacity'],
        ]);
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
        if ($this->isSoldOutRound($match)) {
            $capacity = $this->soldOutCapacity($match);
            if ($capacity > 0) {
                return ['attendance' => $capacity, 'capacity' => $capacity];
            }
        }

        if ($match->isNeutralVenue()) {
            return null;
        }

        $home = Team::find($match->home_team_id);
        if (!$home) {
            return null;
        }

        $competition = $match->competition ?? Competition::find($match->competition_id);

        $homeRep = $this->loadReputation($game->id, $home->id);
        $awayRep = $this->loadReputation($game->id, $match->away_team_id);

        $homePosition = null;
        if ($competition && $competition->role === Competition::ROLE_LEAGUE) {
            $homePosition = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->where('team_id', $home->id)
                ->value('position');
            $homePosition = $homePosition !== null ? (int) $homePosition : null;
        }

        $attendance = $this->demandCurve->project(
            $home,
            $homeRep,
            $awayRep,
            $competition,
            $homePosition,
        );

        return [
            'attendance' => $attendance,
            'capacity' => (int) ($home->stadium_seats ?? 0),
        ];
    }

    private function isSoldOutRound(GameMatch $match): bool
    {
        return in_array($match->round_name, self::SOLD_OUT_ROUNDS, true);
    }

    /**
     * Capacity a final/semi-final plays to: the designated neutral venue
     * when one is set, otherwise the home club's own stadium.
     */
    private function soldOutCapacity(GameMatch $match): int
    {
        if ($match->neutral_venue_capacity !== null) {
            return (int) $match->neutral_venue_capacity;
        }

        return (int) (Team::find($match->home_team_id)?->stadium_seats ?? 0);
    }

    /**
     * Load the game-scoped TeamReputation row, falling back to a synthetic
     * instance seeded from ClubProfile when no row exists (e.g. teams from
     * outside the primary game that occasionally appear in cups).
     */
    private function loadReputation(string $gameId, string $teamId): TeamReputation
    {
        $rep = TeamReputation::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->first();

        if ($rep) {
            return $rep;
        }

        $profile = ClubProfile::where('team_id', $teamId)->first();
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

        return $synthetic;
    }
}
