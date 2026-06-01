<?php

namespace App\Modules\Player\Services;

use App\Modules\Player\PlayerAge;

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
     * Convert market value to a single overall_score.
     *
     * Used during initial seeding to derive ability from Transfermarkt data.
     *
     * @param int $marketValueCents Market value in cents (e.g., 1_500_000_000 = €15M)
     * @param int $age Player's current age
     * @param string|null $position Player's primary position; goalkeepers receive a value boost.
     */
    public function marketValueToOverallScore(int $marketValueCents, int $age, ?string $position = null): int
    {
        $effectiveValue = $this->applyPositionMultiplier($marketValueCents, $position);
        $rawAbility = $this->marketValueToRawAbility($effectiveValue);

        return $this->adjustAbilityForAge($rawAbility, $effectiveValue, $age);
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

        // Clamp to reasonable range: €100K to €150M
        return max(100_000_00, min(150_000_000_00, $newValue));
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
     * Convert market value to a raw ability score via log-linear interpolation.
     *
     * Uses the same anchor points as abilityToBaseValue() but in reverse,
     * making the forward and reverse mappings near-symmetric.
     * Small random variance (±1) prevents identical scores for similar values.
     */
    private function marketValueToRawAbility(int $marketValueCents): int
    {
        $anchors = self::ABILITY_VALUE_ANCHORS;

        if ($marketValueCents <= $anchors[0][1]) {
            return max(40, $anchors[0][0] + rand(-2, 2));
        }

        $last = count($anchors) - 1;
        if ($marketValueCents >= $anchors[$last][1]) {
            return min(99, $anchors[$last][0] + rand(-1, 2));
        }

        for ($i = 0; $i < $last; $i++) {
            [$aLow, $vLow] = $anchors[$i];
            [$aHigh, $vHigh] = $anchors[$i + 1];

            if ($marketValueCents >= $vLow && $marketValueCents <= $vHigh) {
                $t = (log($marketValueCents) - log($vLow)) / (log($vHigh) - log($vLow));
                $ability = $aLow + $t * ($aHigh - $aLow);

                return max(40, min(99, (int) round($ability) + rand(-1, 1)));
            }
        }

        return $anchors[0][0]; // @codeCoverageIgnore — unreachable, anchors are contiguous
    }

    /**
     * Adjust raw ability for age.
     *
     * Young players (< YOUNG_END) are capped by age: market value at this
     * stage signals POTENTIAL, not current ability — a 17yo worth €120M is
     * priced for his ceiling, not for being world-class today. The extra
     * headroom is channelled into potential by
     * PlayerDevelopmentService::generatePotential(), not into current
     * overall.
     *
     * Veterans (PRIME_END - 2 onwards) get an ability boost when their
     * market value proves they're still elite. Different signal: a 33yo
     * worth €40M is staying expensive because he's still delivering at top
     * level, so the market value does map to current ability.
     */
    private function adjustAbilityForAge(int $rawAbility, int $marketValueCents, int $age): int
    {
        if ($age < PlayerAge::YOUNG_END) {
            // Base cap increases with age: 17yo = 75, 22yo = 85. No market-
            // value boost: the headroom flows into potential, not overall.
            $ageCap = 75 + ($age - 17) * 2;

            return min($rawAbility, $ageCap);
        }

        // Boost starts 3 years before official veteran age.
        if ($age <= PlayerAge::PRIME_END - 3) {
            return $rawAbility;
        }

        // Veterans: boost ability if market value proves they're still elite
        $typicalValueForAge = match (true) {
            $age <= 33 => 500_000_000,   // €5M
            $age <= 35 => 300_000_000,   // €3M
            $age <= 37 => 150_000_000,   // €1.5M
            default => 80_000_000,        // €800K
        };

        $valueRatio = $marketValueCents / max(1, $typicalValueForAge);

        $abilityBoost = match (true) {
            $valueRatio >= 10 => 12,
            $valueRatio >= 5 => 8,
            $valueRatio >= 3 => 5,
            $valueRatio >= 2 => 3,
            $valueRatio >= 1 => 1,
            default => 0,
        };

        return min(95, $rawAbility + $abilityBoost);
    }

    /**
     * Deterministic ability-to-market-value mapping via log-linear interpolation.
     *
     * Anchor points are derived from the forward mapping tier boundaries
     * in marketValueToRawAbility(), making this the mathematical inverse.
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
