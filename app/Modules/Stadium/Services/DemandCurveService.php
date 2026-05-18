<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Team;
use App\Models\TeamReputation;

/**
 * Pure deterministic demand curve. Given the home team's identity and the
 * match context (opponent reputation, competition), returns an attendance
 * number capped at stadium capacity.
 *
 * Loyalty primary, reputation as secondary floor:
 *  - base_fill = FILL_FLOOR + (loyalty_points / 100) × FILL_RANGE.
 *    Loyalty drives occupancy; the formula floor (0.50) ensures even a
 *    loyalty-zero club doesn't play to an empty stadium. On top of that,
 *    reputation provides a higher secondary floor for elite/continental
 *    clubs so a marquee brand with crashed loyalty still draws walk-ups.
 *  - Context modifier (opponent reputation, competition weight)
 *    multiplies on top, clamped to [0.85, 1.20].
 *  - Big-game sellout: when the combined context bonus reaches
 *    SELLOUT_BONUS_THRESHOLD, attendance jumps to full capacity. Marquee
 *    visitors and European knockout nights pack the ground regardless of
 *    the home side's day-to-day loyalty level.
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

    // Visitor-reputation floors. The visiting club's brand alone draws a
    // baseline crowd regardless of home-side loyalty: elite/continental
    // names pack stadiums, but even an established or modest opponent in
    // the top flight pulls more than a deserted ground would imply. Floor
    // ladder descends 10 points per tier; a local-tier visitor falls back
    // to the loyalty curve's own 50% floor.
    private const OPPONENT_FLOOR_RATIO = [
        ClubProfile::REPUTATION_ELITE => 0.90,
        ClubProfile::REPUTATION_CONTINENTAL => 0.80,
        ClubProfile::REPUTATION_ESTABLISHED => 0.70,
        ClubProfile::REPUTATION_MODEST => 0.60,
    ];

    // Combined context-bonus (opponentDelta + competitionWeight) at which a
    // match is treated as a guaranteed sellout. 0.10 fires for: any elite
    // visitor against a local/modest tier club in any competition, any
    // European knockout fixture, and strong visitors in European group
    // stage. Routine same-tier league matches stay under the threshold.
    private const SELLOUT_BONUS_THRESHOLD = 0.10;

    /**
     * Season-average attendance for a home team, ignoring per-fixture
     * opponent/competition modifiers. Used by budget projections, which
     * need a single expected gate across the schedule rather than a
     * per-match figure. The opponent/competition modifier is clamped to
     * ±20% and averages near 1.0 across a balanced league schedule, so
     * dropping it keeps projections within a few percent of the
     * fixture-by-fixture sum at a fraction of the queries.
     */
    public function projectBaseline(Team $home, TeamReputation $homeRep, ?int $capacityOverride = null): int
    {
        $capacity = $capacityOverride ?? (int) ($home->stadium_seats ?? 0);
        if ($capacity <= 0) {
            return 0;
        }

        $attendance = (int) round($capacity * $this->baseFillRate($homeRep));
        $floor = (int) max(self::ATTENDANCE_FLOOR_ABSOLUTE, $capacity * self::ATTENDANCE_FLOOR_RATIO);

        return max($floor, min($capacity, $attendance));
    }

    public function project(
        Team $home,
        TeamReputation $homeRep,
        TeamReputation $awayRep,
        Competition $competition,
        ?int $capacityOverride = null,
    ): int {
        $capacity = $capacityOverride ?? (int) ($home->stadium_seats ?? 0);
        if ($capacity <= 0) {
            return 0;
        }

        $baseFill = $this->baseFillRate($homeRep);

        $contextBonus = $this->opponentDelta($homeRep, $awayRep)
            + $this->competitionWeight($competition);

        if ($contextBonus >= self::SELLOUT_BONUS_THRESHOLD) {
            return $capacity;
        }

        $modifier = max(self::MODIFIER_MIN, min(self::MODIFIER_MAX, 1.0 + $contextBonus));

        $attendance = (int) round($capacity * $baseFill * $modifier);

        $floor = (int) max(self::ATTENDANCE_FLOOR_ABSOLUTE, $capacity * self::ATTENDANCE_FLOOR_RATIO);
        $opponentFloor = $this->opponentFloor($awayRep, $capacity);

        return max($floor, $opponentFloor, min($capacity, $attendance));
    }

    /**
     * Visitor-reputation floor on the gate. Each tier sets a minimum fill
     * regardless of home-side loyalty: elite 90%, continental 80%,
     * established 70%, modest 60%. Local-tier visitors fall back to the
     * loyalty curve's own 50% floor.
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
}
