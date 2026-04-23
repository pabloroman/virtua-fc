<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Team;
use App\Models\TeamReputation;

/**
 * Pure deterministic demand curve. Given the home team's identity and the
 * match context (opponent reputation, competition, league position),
 * returns an attendance number capped at stadium capacity.
 *
 * Loyalty primary, reputation as secondary floor:
 *  - base_fill = FILL_FLOOR + (loyalty_points / 100) × FILL_RANGE.
 *    Loyalty drives occupancy; the formula floor (0.50) ensures even a
 *    loyalty-zero club doesn't play to an empty stadium. On top of that,
 *    reputation provides a higher secondary floor for elite/continental
 *    clubs so a marquee brand with crashed loyalty still draws walk-ups.
 *  - Context modifier (opponent reputation, competition weight, home
 *    league position) multiplies on top, clamped to [0.85, 1.20].
 *
 * Calibrated against real La Liga / La Liga 2 occupancy data. With
 * average modifiers (~1.0 for mid-table), the formula produces:
 *   loyalty 9 → ~90%, loyalty 7 → ~82%, loyalty 5 → ~73%,
 *   loyalty 3 → ~64%, loyalty 0 → ~50%.
 */
class DemandCurveService
{
    private const MODIFIER_MIN = 0.85;
    private const MODIFIER_MAX = 1.20;

    // base_fill = FILL_FLOOR + (loyalty_points / 100) × FILL_RANGE
    // Loyalty 0 → 50%; loyalty 100 → 95%.
    private const FILL_FLOOR = 0.50;
    private const FILL_RANGE = 0.45;

    private const ATTENDANCE_FLOOR_RATIO = 0.10;
    private const ATTENDANCE_FLOOR_ABSOLUTE = 500;

    // Opponent-reputation floors. Hosting a marquee visitor draws near-full
    // stadiums regardless of home-side loyalty or form.
    private const OPPONENT_FLOOR_RATIO = [
        ClubProfile::REPUTATION_ELITE => 0.90,
        ClubProfile::REPUTATION_CONTINENTAL => 0.80,
    ];

    public function project(
        Team $home,
        TeamReputation $homeRep,
        TeamReputation $awayRep,
        Competition $competition,
        ?int $homePosition = null,
    ): int {
        $capacity = (int) ($home->stadium_seats ?? 0);
        if ($capacity <= 0) {
            return 0;
        }

        $baseFill = $this->baseFillRate($homeRep);

        $modifier = 1.0
            + $this->opponentDelta($homeRep, $awayRep)
            + $this->competitionWeight($competition)
            + $this->positionBoost($competition, $homePosition);

        $modifier = max(self::MODIFIER_MIN, min(self::MODIFIER_MAX, $modifier));

        $attendance = (int) round($capacity * $baseFill * $modifier);

        $floor = (int) max(self::ATTENDANCE_FLOOR_ABSOLUTE, $capacity * self::ATTENDANCE_FLOOR_RATIO);
        $opponentFloor = $this->opponentFloor($awayRep, $capacity);

        return max($floor, $opponentFloor, min($capacity, $attendance));
    }

    /**
     * Minimum attendance when hosting a top-tier visitor. Elite visitors
     * (Real/Barça/etc.) floor the gate at 90% capacity, continental visitors
     * at 80%. Lower-tier visitors don't trigger a floor — the normal
     * loyalty-driven demand curve applies.
     */
    private function opponentFloor(TeamReputation $awayRep, int $capacity): int
    {
        $ratio = self::OPPONENT_FLOOR_RATIO[$awayRep->reputation_level] ?? 0.0;

        return (int) round($capacity * $ratio);
    }

    /**
     * Loyalty-driven fill rate with a reputation-based secondary floor.
     *
     * The formula maps the 0-100 internal loyalty range to a 50-95%
     * occupancy band. Rayo (loyalty 7 → 70 internal → 81.5%) outpaces
     * Villarreal (loyalty 6 → 60 → 77%), which both outpace Getafe
     * (loyalty 0 → 0 → 50%). An elite club whose loyalty has collapsed
     * beyond the base floor still draws walk-ups via the reputation floor.
     */
    private function baseFillRate(TeamReputation $homeRep): float
    {
        $normalised = max(0, min(100, (int) $homeRep->loyalty_points)) / 100.0;
        $loyaltyFill = self::FILL_FLOOR + $normalised * self::FILL_RANGE;

        $reputationFloor = (float) config(
            "finances.reputation_fill_floor.{$homeRep->reputation_level}",
            0.0,
        );

        return max($loyaltyFill, $reputationFloor);
    }

    /**
     * Bigger visiting clubs draw bigger crowds. Away tier index minus home
     * tier index, scaled and capped so a marquee visit can't single-handedly
     * dominate the modifier chain.
     */
    private function opponentDelta(TeamReputation $homeRep, TeamReputation $awayRep): float
    {
        $homeTier = ClubProfile::getReputationTierIndex($homeRep->reputation_level);
        $awayTier = ClubProfile::getReputationTierIndex($awayRep->reputation_level);

        $diff = $awayTier - $homeTier;

        return max(-0.05, min(0.10, $diff * 0.025));
    }

    /**
     * European nights and cup finals draw bigger crowds than mid-week league
     * games; early cup rounds against lower-division opposition draw smaller
     * ones. Phase 1 uses a coarse role-based weighting.
     */
    private function competitionWeight(Competition $competition): float
    {
        if ($competition->role === Competition::ROLE_EUROPEAN) {
            return $competition->handler_type === 'knockout_cup' ? 0.15 : 0.05;
        }

        if ($competition->role === Competition::ROLE_DOMESTIC_CUP) {
            return -0.05;
        }

        return 0.0; // ROLE_LEAGUE
    }

    /**
     * Top-4 league position pulls a small attendance bump (title/CL chase);
     * relegation zone drags it down. Only applies in league competitions
     * where the position number is meaningful.
     */
    private function positionBoost(Competition $competition, ?int $homePosition): float
    {
        if ($homePosition === null || $competition->role !== Competition::ROLE_LEAGUE) {
            return 0.0;
        }

        if ($homePosition <= 4) {
            return 0.05;
        }

        // Loose relegation-zone heuristic that works for both 20-team
        // (La Liga) and 22-team (Segunda) leagues.
        if ($homePosition >= 18) {
            return -0.05;
        }

        return 0.0;
    }
}
