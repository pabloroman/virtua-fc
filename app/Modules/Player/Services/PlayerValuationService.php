<?php

namespace App\Modules\Player\Services;

use App\Modules\Player\PlayerAge;
use App\Support\Money;

/**
 * Single source of truth for all overall_score <-> market value conversions.
 *
 * Consolidates logic previously fragmented across:
 * - SeedReferenceData (market value -> ability during seeding)
 * - PlayerDevelopmentService::calculateMarketValue() (ability -> market value)
 * - PlayerGeneratorService::estimateMarketValue() (ability -> market value for generated players)
 *
 * Uses the player's stable overall_score column. Fitness and morale are
 * transient and must not permanently affect valuation.
 */
class PlayerValuationService
{
    private const ABILITY_VALUE_ANCHORS = [
        [45, 10_000_000],        // €100K
        [50, 30_000_000],        // €300K
        [58, 100_000_000],       // €1M
        [63, 200_000_000],       // €2M
        [68, 500_000_000],       // €5M
        [73, 1_000_000_000],     // €10M
        [78, 2_500_000_000],     // €25M
        [83, 5_000_000_000],     // €50M
        [88, 8_000_000_000],     // €80M
        [92, 12_000_000_000],    // €120M
        [95, 15_000_000_000],    // €150M
    ];

    /**
     * Goalkeepers trade at structurally lower market values than outfielders
     * for equivalent quality (top GKs cap around €40M while top outfielders
     * push past €120M). Without compensation, the value→overall map rates
     * world-class keepers like Courtois or Raya as merely good. Scale GK
     * market value up before the mapping (and back down on the inverse) so
     * scores reflect on-pitch ability rather than transfer-market quirks.
     */
    private const GOALKEEPER_VALUE_MULTIPLIER = 2.0;

    /**
     * Second value→ability curve used ONLY by the value→overall fallback when
     * the `competence_floor` correction is engaged. Anchor abilities are the
     * per-bucket median SoFIFA overall for prime-age (23–29) outfielders — an
     * empirical "this is how a player at this price actually plays" curve whose
     * floor sits far higher than ABILITY_VALUE_ANCHORS (a €500k pro is ~67, not
     * ~50). Blended against the economy curve by the correction strength, so a
     * higher competence_floor lifts cheap players toward a competent baseline.
     * Age-27 baseline; age is handled separately in adjustAbilityForAge().
     */
    private const COMPETENCE_FLOOR_ANCHORS = [
        [55, 5_000_000],        // €50k
        [62, 10_000_000],       // €100k
        [67, 50_000_000],       // €500k
        [69, 100_000_000],      // €1M
        [71, 200_000_000],      // €2M
        [73, 400_000_000],      // €4M
        [75, 800_000_000],      // €8M
        [77, 1_600_000_000],    // €16M
        [80, 3_100_000_000],    // €31M
        [84, 6_000_000_000],    // €60M
        [88, 10_000_000_000],   // €100M
        [92, 18_000_000_000],   // €180M (SoFIFA tops out ~91; replaces the economy curve's 95)
    ];

    /**
     * Overall points credited per year past 27 when `skill_persistence` is
     * fully engaged. Fitted against SoFIFA: for a fixed market value an older
     * player rates higher, because his price (not his skill) is what fell with
     * age. A 34-year-old recovers ~+4. Blended against the legacy veteran boost
     * by the correction strength.
     */
    private const SKILL_PERSISTENCE_SLOPE = 0.60;

    /** Hard ceiling on the age-based ability credit, from either mechanism. */
    private const MAX_AGE_BOOST = 12;

    /**
     * Convert market value to a single overall_score.
     *
     * Used during initial seeding to derive ability from Transfermarkt data,
     * ONLY when a player has no imported SoFIFA score. Market value is a biased
     * proxy for current ability; two configurable corrections (see
     * config/player.php → market_value_corrections) counter its known
     * distortions, each blending from 0.0 (raw proxy) to 1.0 (fully corrected).
     *
     * @param int $marketValueCents Market value in cents (e.g., 1_500_000_000 = €15M)
     * @param int $age Player's current age
     * @param string|null $position Player's primary position; goalkeepers receive a value boost.
     * @param float|null $competenceFloor Strength of the low-value floor correction (null → config).
     * @param float|null $skillPersistence Strength of the age-decay correction (null → config).
     */
    public function marketValueToOverallScore(
        int $marketValueCents,
        int $age,
        ?string $position = null,
        ?float $competenceFloor = null,
        ?float $skillPersistence = null
    ): int {
        $competenceFloor = $this->resolveCorrection($competenceFloor, 'competence_floor');
        $skillPersistence = $this->resolveCorrection($skillPersistence, 'skill_persistence');

        $effectiveValue = $this->applyPositionMultiplier($marketValueCents, $position);

        // Blend the economy's value→ability proxy with a higher-floored,
        // SoFIFA-derived curve. At competence_floor = 0 the proxy is used
        // unchanged (cheap means weak); at 1 the low end lifts toward a
        // competent professional baseline. The curves converge at the top, so
        // the correction only moves low/mid-value players.
        $rawProxy = $this->interpolateAbility($effectiveValue, self::ABILITY_VALUE_ANCHORS);
        $rawFloored = $this->interpolateAbility($effectiveValue, self::COMPETENCE_FLOOR_ANCHORS);
        $rawAbility = (1.0 - $competenceFloor) * $rawProxy + $competenceFloor * $rawFloored;

        $overall = $this->adjustAbilityForAge($rawAbility, $effectiveValue, $age, $skillPersistence);

        // Small random variance (±1) prevents identical scores for similar inputs.
        return max(40, min(99, $overall + rand(-1, 1)));
    }

    /**
     * Resolve a market-value correction strength to a clamped [0,1] float,
     * falling back to config when the caller passes null.
     */
    private function resolveCorrection(?float $value, string $key): float
    {
        $value ??= (float) config("player.market_value_corrections.{$key}", 0.0);

        return max(0.0, min(1.0, $value));
    }

    /**
     * Convert overall_score to market value.
     *
     * Used after season-end development, for generated players, etc.
     *
     * @param int $overallScore Player's overall ability score
     * @param int $age Player's current age
     * @param int|null $previousOverall Previous season's overall_score (for performance trend). Only passed during season-end.
     * @param string|null $position Player's primary position; goalkeepers' market value is scaled down to match real-world levels.
     * @return int Market value in cents
     */
    public function overallScoreToMarketValue(int $overallScore, int $age, ?int $previousOverall = null, ?string $position = null): int
    {
        // Deterministic base value via log-linear interpolation of forward mapping anchors
        $baseValue = $this->abilityToBaseValue($overallScore);

        // Age multiplier (reduced youth premiums for realistic valuations)
        $ageMultiplier = $this->ageValueMultiplier($age);

        // Performance trend multiplier (only during season-end)
        $trendMultiplier = 1.0;
        if ($previousOverall !== null) {
            $change = $overallScore - $previousOverall;

            if ($age <= PlayerAge::YOUNG_END && $change > 0) {
                // Young players who improve get a modest boost (confirming potential)
                $trendMultiplier = match (true) {
                    $change >= 5 => 1.2,
                    $change >= 3 => 1.1,
                    default => 1.0,
                };
            } elseif ($change < 0) {
                // Declining players lose value faster
                $trendMultiplier = match (true) {
                    $change <= -4 => 0.7,
                    $change <= -2 => 0.85,
                    default => 0.95,
                };
            }
        }

        $newValue = (int) round($baseValue * $ageMultiplier * $trendMultiplier);

        // Goalkeepers trade below outfielders at the same ability — scale
        // down so the inverse roughly mirrors marketValueToOverallScore().
        if ($this->isGoalkeeper($position)) {
            $newValue = (int) round($newValue / self::GOALKEEPER_VALUE_MULTIPLIER);
        }

        // Clamp to reasonable range (€100K to €150M), then snap to a "nice" round number.
        return Money::roundPrice(max(100_000_00, min(150_000_000_00, $newValue)));
    }

    /**
     * Value used to anchor WAGE demands to a player's *current* ability rather
     * than their (potential-inflated) market value.
     *
     * Identical to overallScoreToMarketValue() except the youth premium is
     * stripped: the age multiplier is capped at 1.0 so a wonderkid is priced
     * for who he is today, not for his ceiling — the headroom lives in
     * potential, and wages should not pay for it. The veteran decline is
     * deliberately preserved (multiplier < 1.0), because the veteran wage
     * modifier in ContractService is calibrated against that depressed value.
     *
     * @param int $overallScore Player's current overall ability score
     * @param int $age Player's current age
     * @param string|null $position Primary position; goalkeepers are scaled like market value
     * @return int Wage-anchoring value in cents
     */
    public function wageBaseValue(int $overallScore, int $age, ?string $position = null): int
    {
        $value = (int) round($this->abilityToBaseValue($overallScore) * min(1.0, $this->ageValueMultiplier($age)));

        if ($this->isGoalkeeper($position)) {
            $value = (int) round($value / self::GOALKEEPER_VALUE_MULTIPLIER);
        }

        // Clamp to the same range as overallScoreToMarketValue().
        return max(100_000_00, min(150_000_000_00, $value));
    }

    /**
     * Age multiplier applied to a player's ability-derived base value. Young
     * players carry a premium (priced for their ceiling), veterans a discount.
     */
    private function ageValueMultiplier(int $age): float
    {
        return match (true) {
            $age <= 19 => 1.3,
            $age <= 21 => 1.2,
            $age <= 23 => 1.1,
            $age <= 26 => 1.05,
            $age <= 31 => 1.0,
            $age <= 33 => 0.75,
            $age <= 35 => 0.45,
            $age <= 37 => 0.30,
            default => 0.15,
        };
    }

    private function applyPositionMultiplier(int $marketValueCents, ?string $position): int
    {
        if (!$this->isGoalkeeper($position)) {
            return $marketValueCents;
        }

        return (int) round($marketValueCents * self::GOALKEEPER_VALUE_MULTIPLIER);
    }

    private function isGoalkeeper(?string $position): bool
    {
        if ($position === null) {
            return false;
        }

        $normalized = strtolower(trim($position));

        return $normalized === 'goalkeeper' || $normalized === 'gk';
    }

    /**
     * Deterministic log-linear interpolation of an ability score from a market
     * value against the given anchor table. Returns a float so the two forward
     * curves can be blended before rounding; the ±1 jitter is applied once by
     * the caller.
     *
     * @param array<int, array{int, int}> $anchors [ability, valueCents] points, ascending.
     */
    private function interpolateAbility(int $marketValueCents, array $anchors): float
    {
        if ($marketValueCents <= $anchors[0][1]) {
            return (float) $anchors[0][0];
        }

        $last = count($anchors) - 1;
        if ($marketValueCents >= $anchors[$last][1]) {
            return (float) $anchors[$last][0];
        }

        for ($i = 0; $i < $last; $i++) {
            [$aLow, $vLow] = $anchors[$i];
            [$aHigh, $vHigh] = $anchors[$i + 1];

            if ($marketValueCents >= $vLow && $marketValueCents <= $vHigh) {
                $t = (log($marketValueCents) - log($vLow)) / (log($vHigh) - log($vLow));

                return $aLow + $t * ($aHigh - $aLow);
            }
        }

        return (float) $anchors[0][0]; // @codeCoverageIgnore — unreachable, anchors are contiguous
    }

    /**
     * Adjust raw ability for age.
     *
     * Young players (< YOUNG_END) are capped by age: market value at this
     * stage signals POTENTIAL, not current ability — a 17yo worth €120M is
     * priced for his ceiling, not for being world-class today. The extra
     * headroom is channelled into potential by
     * PlayerDevelopmentService::generatePotential(), not into current
     * overall. This cap is independent of the corrections below.
     *
     * From prime onwards, the age credit blends two views of how a player's
     * market value relates to his ability as he ages, by `skillPersistence`:
     *  - 0.0 → the legacy veteran boost: only elite-for-their-age veterans
     *    (high value relative to typical) are credited.
     *  - 1.0 → a continuous slope crediting every year past 27, recovering the
     *    ability a player's falling price shed to age rather than to decline.
     */
    private function adjustAbilityForAge(float $rawAbility, int $effectiveValueCents, int $age, float $skillPersistence): int
    {
        if ($age < PlayerAge::YOUNG_END) {
            // Base cap increases with age: 17yo = 75, 22yo = 85. No market-
            // value boost: the headroom flows into potential, not overall.
            $ageCap = 75 + ($age - 17) * 2;

            return (int) round(min($rawAbility, (float) $ageCap));
        }

        $legacyBoost = $this->legacyVeteranBoost($effectiveValueCents, $age);
        $persistenceBoost = self::SKILL_PERSISTENCE_SLOPE * max(0, $age - 27);

        $boost = min(
            (float) self::MAX_AGE_BOOST,
            (1.0 - $skillPersistence) * $legacyBoost + $skillPersistence * $persistenceBoost
        );

        return (int) round(min(95.0, $rawAbility + $boost));
    }

    /**
     * Legacy veteran ability boost (the `skillPersistence = 0` endpoint):
     * credit ability only when an ageing player stays expensive relative to a
     * typical value for his age — proof he's still delivering at top level.
     * Returns 0 below the veteran threshold (PRIME_END - 3).
     */
    private function legacyVeteranBoost(int $effectiveValueCents, int $age): int
    {
        if ($age <= PlayerAge::PRIME_END - 3) {
            return 0;
        }

        $typicalValueForAge = match (true) {
            $age <= 33 => 500_000_000,   // €5M
            $age <= 35 => 300_000_000,   // €3M
            $age <= 37 => 150_000_000,   // €1.5M
            default => 80_000_000,        // €800K
        };

        $valueRatio = $effectiveValueCents / max(1, $typicalValueForAge);

        return match (true) {
            $valueRatio >= 10 => 12,
            $valueRatio >= 5 => 8,
            $valueRatio >= 3 => 5,
            $valueRatio >= 2 => 3,
            $valueRatio >= 1 => 1,
            default => 0,
        };
    }

    /**
     * Deterministic ability-to-market-value mapping via log-linear interpolation.
     *
     * Anchor points are the ABILITY_VALUE_ANCHORS tiers read in reverse,
     * making this the mathematical inverse of the economy's forward curve.
     * Interpolation in log-space produces smooth exponential growth between anchors.
     *
     * @param int $ability Overall ability score
     * @return int Market value in cents
     */
    private function abilityToBaseValue(int $ability): int
    {
        $anchors = self::ABILITY_VALUE_ANCHORS;

        if ($ability <= $anchors[0][0]) {
            return $anchors[0][1];
        }

        $last = count($anchors) - 1;
        if ($ability >= $anchors[$last][0]) {
            return $anchors[$last][1];
        }

        for ($i = 0; $i < $last; $i++) {
            [$aLow, $vLow] = $anchors[$i];
            [$aHigh, $vHigh] = $anchors[$i + 1];

            if ($ability >= $aLow && $ability <= $aHigh) {
                $t = ($ability - $aLow) / ($aHigh - $aLow);
                $logValue = log($vLow) + $t * (log($vHigh) - log($vLow));

                return (int) round(exp($logValue));
            }
        }

        return $anchors[0][1]; // @codeCoverageIgnore — unreachable, anchors are contiguous
    }
}
