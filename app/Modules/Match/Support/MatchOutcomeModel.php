<?php

namespace App\Modules\Match\Support;

/**
 * Pure, stateless model of a match outcome: the difference-based expected-goals
 * formula plus the Dixon-Coles correlated-Poisson scoreline distribution.
 *
 * This is the SHARED source of the simulation's outcome math. {@see AIMatchResolver}
 * resolves AI-vs-AI matches through these methods and {@see MatchSimulator}
 * delegates its base xG to `expectedGoals()` (then layers per-minute fractions
 * and tactical modifiers on top), so the kernel lives in exactly one place and
 * any season-realism diagnostic calling these methods exercises production math.
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

    /** Floor on each side's xG so a Poisson distribution stays well-formed. */
    private const MIN_TEAM_XG = 0.15;

    /**
     * Expected goals for each side from the difference-based supremacy model:
     *
     *   d         = (homeStrength − awayStrength) × 100   (rating-point gap)
     *   supremacy = d / goal_supremacy_scale              (goals of home edge)
     *   homeXG    = base_goals + supremacy/2 + home_advantage
     *   awayXG    = base_goals − supremacy/2
     *
     * Strength is `mean(rating)/100`, so the difference is read back in rating
     * points. Using a difference rather than a ratio means there is no arbitrary
     * zero to rescue (no floor) and the stronger team's edge keeps growing with
     * the gap instead of being renormalised — a dominant squad pulls clear. Each
     * side is floored at MIN_TEAM_XG so the Poisson sampling stays well-formed.
     *
     * `$overrides` lets callers preview a different `goal_supremacy_scale` /
     * `base_goals` / `home_advantage_goals` without mutating config (used by the
     * strength-realism diagnostic).
     *
     * @param  array{goal_supremacy_scale?: float, base_goals?: float, home_advantage_goals?: float}  $overrides
     * @return array{0: float, 1: float}  [homeXG, awayXG]
     */
    public static function expectedGoals(
        float $homeStrength,
        float $awayStrength,
        bool $neutralVenue = false,
        array $overrides = [],
    ): array {
        $baseGoals = $overrides['base_goals'] ?? (float) config('match_simulation.base_goals', 1.4);
        $scale = $overrides['goal_supremacy_scale'] ?? (float) config('match_simulation.goal_supremacy_scale', 10.0);
        $homeAdvantage = $neutralVenue
            ? 0.0
            : ($overrides['home_advantage_goals'] ?? (float) config('match_simulation.home_advantage_goals', 0.20));

        // Rating-point gap → goals of home-minus-away supremacy, split evenly
        // around the even-match baseline. A guard against a non-positive scale
        // keeps the formula finite.
        $supremacy = $scale > 0.0 ? (($homeStrength - $awayStrength) * 100.0) / $scale : 0.0;

        $homeXG = max(self::MIN_TEAM_XG, $baseGoals + $supremacy / 2.0 + $homeAdvantage);
        $awayXG = max(self::MIN_TEAM_XG, $baseGoals - $supremacy / 2.0);

        return [$homeXG, $awayXG];
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
