<?php

namespace App\Modules\Match\Support;

/**
 * Pure, stateless model of a match outcome: the ratio-based expected-goals
 * formula plus the Dixon-Coles correlated-Poisson scoreline distribution.
 *
 * This is the SHARED source of the simulation's outcome math. {@see AIMatchResolver}
 * resolves AI-vs-AI matches through these methods, so any season-realism
 * diagnostic that calls them is exercising the exact production formula rather
 * than a copy. {@see MatchSimulator} still carries its own equivalent
 * implementation (it threads per-minute match fractions and tactical modifiers
 * through the same shape) and can adopt this helper in a later pass.
 *
 * Beyond sampling a scoreline (what the resolver needs), this helper exposes
 * `outcomeProbabilities()` — the analytical P(win)/P(draw)/P(loss) collapse of
 * the score matrix — which neither resolver nor simulator computed before. That
 * is what lets a diagnostic read win probabilities and expected league points
 * straight from the model, with no Monte-Carlo noise.
 */
class MatchOutcomeModel
{
    /** Score matrix is evaluated for 0..MAX_GOALS per team before capping. */
    private const MAX_GOALS = 8;

    /** Precomputed factorials for the Poisson PMF, indices 0..MAX_GOALS. */
    private const FACTORIALS = [1, 1, 2, 6, 24, 120, 720, 5040, 40320];

    /**
     * Expected goals for each side from the ratio-based power formula:
     *
     *   homeXG = (homeStr/awayStr) ^ skill_dominance × base_goals + home_advantage
     *   awayXG = (awayStr/homeStr) ^ skill_dominance × base_goals
     *
     * The stronger team is always favoured; the exponent controls how sharply a
     * strength ratio widens the xG gap. `$overrides` lets callers preview a
     * different `skill_dominance` / `base_goals` / `home_advantage_goals` without
     * mutating config (used by the strength-realism diagnostic).
     *
     * @param  array{skill_dominance?: float, base_goals?: float, home_advantage_goals?: float}  $overrides
     * @return array{0: float, 1: float}  [homeXG, awayXG]
     */
    public static function expectedGoals(
        float $homeStrength,
        float $awayStrength,
        bool $neutralVenue = false,
        array $overrides = [],
    ): array {
        $baseGoals = $overrides['base_goals'] ?? (float) config('match_simulation.base_goals', 1.4);
        $skillDominance = $overrides['skill_dominance'] ?? (float) config('match_simulation.skill_dominance', 2.4);
        $homeAdvantage = $neutralVenue
            ? 0.0
            : ($overrides['home_advantage_goals'] ?? (float) config('match_simulation.home_advantage_goals', 0.20));

        // Degenerate opponent (no players / zero strength): give the present side
        // a base attack plus home edge, the empty side a token half-base.
        if ($awayStrength <= 0) {
            return [$baseGoals + $homeAdvantage, $baseGoals * 0.5];
        }

        $strengthRatio = self::clampStrengthRatio($homeStrength / $awayStrength);
        $homeXG = pow($strengthRatio, $skillDominance) * $baseGoals + $homeAdvantage;
        $awayXG = pow(1.0 / $strengthRatio, $skillDominance) * $baseGoals;

        return [$homeXG, $awayXG];
    }

    /**
     * Bound a home/away strength ratio before it is raised to `skill_dominance`.
     *
     * The xG power formula has no inherent ceiling: a ratio of 13 at exponent 2.4
     * is ~700× base goals. Team strength is floored on STATIC ability (see
     * {@see applyFloor} and MatchSimulator::calculateTeamStrength), so within a
     * league the ratio stays anchored to the floor's calibrated band (R≈1.34) and
     * this clamp does not bind there. Its job is genuine CROSS-LEAGUE mismatches —
     * a top-flight side vs a lower-league team in a cup tie, whose static ratio
     * legitimately runs higher — where it keeps a single match from running away
     * (`max_strength_ratio` → worst-case xG ≈ max^skill_dominance × base_goals).
     *
     * Clamping symmetrically to `[1/max, max]` is the model's single ratio bound,
     * applied uniformly wherever xG is computed — so every code path (full
     * simulator, live resimulation, extra time, AI-vs-AI) inherits it from one
     * place. `max_strength_ratio` ≤ 1.0 (e.g. 0) disables the clamp — a no-op
     * escape hatch mirroring the floor's own convention.
     */
    public static function clampStrengthRatio(float $ratio): float
    {
        $max = (float) config('match_simulation.max_strength_ratio', 2.2);

        if ($max <= 1.0) {
            return $ratio;
        }

        return min($max, max(1.0 / $max, $ratio));
    }

    /**
     * Rescale a team strength against a baseline floor on the 0..100 rating
     * scale, where `rating = strength * 100`:
     *
     *   strength' = (rating - floor) / (100 - floor)
     *
     * This re-expands the ratio between teams whose ratings sit in a high,
     * narrow band. No real squad rates below ~50, so the bottom of the 0..100
     * strength scale is dead weight that squashes every ratio toward 1.0 and
     * makes matches coin flips; subtracting a floor stretches the relative gaps
     * back out. Apply the SAME floor to both teams in a match before forming
     * their strength ratio.
     *
     * Apply this to STATIC ability, not to match-time-eroded strength. The floor
     * is calibrated on static top-11 overall_score and derived to sit at least
     * strength_floor_margin below the weakest squad, so static ability never
     * reaches the 0.02 clamp below. Feeding it strength already eroded by form,
     * fatigue or out-of-position penalties (the pre-#1283 behaviour) let a side
     * fall through the floor and explode the ratio; callers apply match-time
     * modifiers as multipliers AFTER flooring instead.
     *
     * A floor of 0 (or an out-of-range floor) is an exact no-op, so callers that
     * don't resolve a floor keep the original behaviour.
     */
    public static function applyFloor(float $strength, float $floor): float
    {
        if ($floor <= 0.0 || $floor >= 100.0) {
            return $strength;
        }

        $rescaled = ($strength * 100.0 - $floor) / (100.0 - $floor);

        // Keep strictly positive so the home/away ratio stays finite even for a
        // squad rated at or below the floor (e.g. a heavily depleted XI).
        return max(0.02, $rescaled);
    }

    /**
     * Build the normalized Dixon-Coles scoreline probability matrix.
     *
     * Each entry is [homeGoals, awayGoals, probability] with probabilities
     * summing to 1.0. Independent Poisson is corrected by the Dixon-Coles `tau`
     * factor for low scores and then sharpened by `score_concentration` (an
     * inverse-temperature power transform) before renormalization.
     *
     * @return array<int, array{0: int, 1: int, 2: float}>
     */
    public static function scoreProbabilityMatrix(float $homeXG, float $awayXG): array
    {
        $rho = (float) config('match_simulation.dixon_coles_rho', -0.13);
        $concentration = (float) config('match_simulation.score_concentration', 1.0);

        $matrix = [];
        $total = 0.0;

        for ($i = 0; $i <= self::MAX_GOALS; $i++) {
            $pHome = self::poissonPmf($i, $homeXG);
            for ($j = 0; $j <= self::MAX_GOALS; $j++) {
                $pAway = self::poissonPmf($j, $awayXG);
                $tau = self::dixonColesTau($i, $j, $homeXG, $awayXG, $rho);
                $p = $pHome * $pAway * $tau;

                if ($concentration !== 1.0) {
                    $p = $p ** $concentration;
                }

                $matrix[] = [$i, $j, $p];
                $total += $p;
            }
        }

        if ($total > 0.0) {
            foreach ($matrix as &$entry) {
                $entry[2] /= $total;
            }
            unset($entry);
        }

        return $matrix;
    }

    /**
     * Collapse a (normalized) score matrix into analytical outcome probabilities
     * and expected goals for each side.
     *
     * @param  array<int, array{0: int, 1: int, 2: float}>  $matrix
     * @return array{home: float, draw: float, away: float, homeGoals: float, awayGoals: float}
     */
    public static function outcomeProbabilities(array $matrix): array
    {
        $home = 0.0;
        $draw = 0.0;
        $away = 0.0;
        $homeGoals = 0.0;
        $awayGoals = 0.0;

        foreach ($matrix as [$i, $j, $p]) {
            if ($i > $j) {
                $home += $p;
            } elseif ($i === $j) {
                $draw += $p;
            } else {
                $away += $p;
            }
            $homeGoals += $i * $p;
            $awayGoals += $j * $p;
        }

        return [
            'home' => $home,
            'draw' => $draw,
            'away' => $away,
            'homeGoals' => $homeGoals,
            'awayGoals' => $awayGoals,
        ];
    }

    /**
     * Draw a scoreline from a (normalized) score matrix. Consumes exactly one
     * mt_rand() call — the same random-stream footprint as the resolver's
     * previous inline sampler, so seeded runs stay reproducible.
     *
     * @param  array<int, array{0: int, 1: int, 2: float}>  $matrix
     * @return array{0: int, 1: int}  [homeGoals, awayGoals]
     */
    public static function sampleScore(array $matrix): array
    {
        $rand = mt_rand() / mt_getrandmax();

        $cumulative = 0.0;
        foreach ($matrix as [$home, $away, $p]) {
            $cumulative += $p;
            if ($rand <= $cumulative) {
                return [$home, $away];
            }
        }

        return [0, 0];
    }

    /**
     * Convenience: build the matrix and sample a scoreline in one call.
     *
     * @return array{0: int, 1: int}  [homeGoals, awayGoals]
     */
    public static function sampleScoreline(float $homeXG, float $awayXG): array
    {
        return self::sampleScore(self::scoreProbabilityMatrix($homeXG, $awayXG));
    }

    /**
     * Poisson probability mass function: P(X = k) given expected value lambda.
     */
    private static function poissonPmf(int $k, float $lambda): float
    {
        if ($lambda <= 0) {
            return $k === 0 ? 1.0 : 0.0;
        }

        return exp(-$lambda) * pow($lambda, $k) / self::FACTORIALS[$k];
    }

    /**
     * Dixon-Coles tau correction. Only adjusts the four low-scoring outcomes
     * (0-0, 1-0, 0-1, 1-1); tau = 1 everywhere else.
     */
    private static function dixonColesTau(int $homeGoals, int $awayGoals, float $homeXG, float $awayXG, float $rho): float
    {
        if ($homeGoals === 0 && $awayGoals === 0) {
            return 1.0 - $homeXG * $awayXG * $rho;
        }
        if ($homeGoals === 1 && $awayGoals === 0) {
            return 1.0 + $awayXG * $rho;
        }
        if ($homeGoals === 0 && $awayGoals === 1) {
            return 1.0 + $homeXG * $rho;
        }
        if ($homeGoals === 1 && $awayGoals === 1) {
            return 1.0 - $rho;
        }

        return 1.0;
    }
}
