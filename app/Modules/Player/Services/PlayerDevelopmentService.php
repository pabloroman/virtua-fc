<?php

namespace App\Modules\Player\Services;

use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\DevelopmentCurve;

/**
 * Core service handling all player development logic.
 *
 * Responsible for:
 * - Calculating age-based development rates
 * - Generating potential for new players (influenced by market value)
 * - Projecting future ability development
 *
 * Key principles:
 * - Young players with high market value have proven higher potential
 * - Veterans with exceptional market value have proven their quality ceiling
 * - Match playing time accelerates growth; bench players still develop in training at a reduced rate
 */
class PlayerDevelopmentService
{
    /**
     * Calculate development for a single player.
     *
     * Development is influenced by:
     * - Age (young players grow, veterans decline)
     * - Playing time (full growth at FULL_BONUS_APPEARANCES, training-only rate below MIN_APPEARANCES_FOR_GROWTH)
     * - Quality gap (young players far from potential get +1 bonus)
     * - Potential cap (players can't exceed their ceiling)
     *
     * @return array{
     *     before: int,
     *     after: int,
     *     change: int,
     * }
     */
    public function calculateDevelopment(GamePlayer $player, ?int $precomputedAge = null): array
    {
        $age = $precomputedAge ?? $player->age($player->game->current_date);
        $appearances = $player->season_appearances;

        $current = $player->overall_score;
        $potential = $player->potential ?? 99;

        $baseChange = DevelopmentCurve::getChange($age);
        $change = DevelopmentCurve::calculateChange($baseChange, $appearances);

        // Quality gap: flat +1 bonus for young players far from potential
        $gapBonus = $this->calculateQualityGapBonus($current, $potential, $age);
        if ($change > 0) {
            $change += $gapBonus;
        }

        // Calculate new ability
        $newOverall = $current + $change;

        // Cap at potential (only for growth, not decline)
        if ($change > 0) {
            $newOverall = min($newOverall, $potential);
        }

        // Ensure ability stays within valid range (1-99)
        $newOverall = max(1, min(99, $newOverall));

        return [
            'before' => $current,
            'after' => $newOverall,
            'change' => $newOverall - $current,
        ];
    }

    /**
     * Tiered bonus for young players still climbing toward potential.
     *
     * Delegates to DevelopmentCurve::gapBonus so the rule lives in one place
     * (also called by PlayerDevelopmentProcessor).
     */
    private function calculateQualityGapBonus(int $currentAbility, int $potential, int $age): int
    {
        return DevelopmentCurve::gapBonus($age, $currentAbility, $potential);
    }

    /**
     * Generate potential for a new player based on age, current ability, and market value.
     *
     * Market value is used as a proxy for "proven" ceiling on young/peak players —
     * a 17yo worth €80M has demonstrated elite upside even before maturing.
     * Veterans (age > PRIME_END) are clamped to their current ability: the
     * development curve has no upward path for them, so any displayed
     * headroom would be unreachable in practice.
     *
     * @param int $age Player's current age
     * @param int $currentAbility Player's current overall ability
     * @param int $marketValueCents Player's market value in cents (e.g., €15M = 1500000000)
     * @return array{potential: int, low: int, high: int}
     */
    public function generatePotential(int $age, int $currentAbility, int $marketValueCents = 0): array
    {
        $valueBonus = $this->getValuePotentialBonus($age, $marketValueCents);

        if ($age <= PlayerAge::ACADEMY_END) {
            // Young players: high potential ceiling
            // Base range 8-20, plus value bonus for proven youngsters
            $basePotentialRange = rand(8, 20);
            $potentialRange = $basePotentialRange + $valueBonus;
            $uncertainty = rand(5, 10); // Higher uncertainty for young players
        } elseif ($age <= 24) {
            // Developing players: moderate potential
            $basePotentialRange = rand(4, 12);
            $potentialRange = $basePotentialRange + (int) ($valueBonus * 0.6);
            $uncertainty = rand(4, 7);
        } elseif ($age <= PlayerAge::PRIME_END) {
            // Peak players: small headroom over current ability — elite peak
            // players (high market value) get a slightly higher visible ceiling.
            $basePotentialRange = rand(0, 2);
            $potentialRange = $basePotentialRange + (int) ($valueBonus * 0.3);
            $uncertainty = rand(1, 2);
        } else {
            // Veterans: no upside left — current ability IS the ceiling.
            // UI hides the potential row for this branch (see player-detail view).
            $potentialRange = 0;
            $uncertainty = 0;
        }

        // Diminishing returns for high-rated players past their growing
        // phase. Without this taper the developing/peak branches push too
        // many already-strong players to 95-99 ceilings, which is unrealistic
        // on a population basis.
        if ($age > PlayerAge::YOUNG_END && $currentAbility >= 80) {
            $taperFactor = match (true) {
                $currentAbility >= 88 => 0.25,
                $currentAbility >= 84 => 0.5,
                default => 0.75,
            };
            $potentialRange = (int) round($potentialRange * $taperFactor);
            $uncertainty = (int) round($uncertainty * $taperFactor);
        }

        // True potential (hidden from user). Clamp to what the development
        // curve can actually deliver from this age — otherwise displayed
        // potentials in the 90s become structurally unreachable.
        $reachableCeiling = min(99, $currentAbility + DevelopmentCurve::maxLifetimeGrowth($age));
        $truePotential = min(99, $reachableCeiling, $currentAbility + $potentialRange);

        // Scouted range (visible to user) — adds uncertainty around true value
        $low = max($currentAbility, $truePotential - $uncertainty);
        $high = min(99, $reachableCeiling, $truePotential + $uncertainty);

        return [
            'potential' => $truePotential,
            'low' => $low,
            'high' => $high,
        ];
    }

    /**
     * Calculate potential bonus based on market value relative to age.
     *
     * @return int Bonus points to add to potential range (0-12)
     */
    private function getValuePotentialBonus(int $age, int $marketValueCents): int
    {
        // Veterans (PRIME_END+1 and up) have no upside left and skip this
        // branch entirely. Players from age 29 onwards also get no value
        // bonus since their ceiling is essentially fixed at current ability.
        if ($age >= 29) {
            return 0;
        }

        // Typical market value for age (what an "average good player" is worth)
        $typicalValueForAge = match (true) {
            $age <= 17 => 50_000_000,       // €500K
            $age <= 19 => 200_000_000,      // €2M
            $age <= 21 => 500_000_000,      // €5M
            $age <= 23 => 1_000_000_000,    // €10M
            $age <= 25 => 1_500_000_000,    // €15M
            default => 2_000_000_000,        // €20M
        };

        $valueRatio = $marketValueCents / max(1, $typicalValueForAge);

        // Young players: PlayerValuationService::adjustAbilityForAge() no
        // longer raises the age cap on current ability for high-market-value
        // youngsters — that headroom now lives in potential instead. Bonus
        // kicks in at lower value ratios so a 19yo who's merely "expensive
        // for his age" still gains potential, not just the megastars.
        if ($age <= PlayerAge::YOUNG_END) {
            return match (true) {
                $valueRatio >= 100 => 12, // e.g. €120M 17yo (240x typical)
                $valueRatio >= 50 => 10,
                $valueRatio >= 20 => 8,
                $valueRatio >= 10 => 6,
                $valueRatio >= 5 => 4,
                $valueRatio >= 2 => 2,
                $valueRatio >= 1 => 1,
                default => 0,
            };
        }

        // Higher ratio = more proven potential
        return match (true) {
            $valueRatio >= 100 => 10, // e.g. €120M 17yo (240x typical) = elite potential
            $valueRatio >= 50 => 8,
            $valueRatio >= 20 => 6,
            $valueRatio >= 10 => 4,
            $valueRatio >= 5 => 2,
            default => 0,
        };
    }

    /**
     * Project player's future ability development.
     *
     * Assumes the player will be a regular starter (optimistic projection).
     *
     * @param int $seasons Number of seasons to project
     * @return array Array of projections per season
     */
    public function projectDevelopment(GamePlayer $player, int $seasons = 3): array
    {
        $projections = [];
        $currentOverall = $player->overall_score;
        $currentAge = $player->age($player->game->current_date);
        $potential = $player->potential ?? 99;

        // Assume regular starter for optimistic projection
        $assumedAppearances = DevelopmentCurve::FULL_BONUS_APPEARANCES;

        for ($i = 1; $i <= $seasons; $i++) {
            $age = $currentAge + $i;
            $baseChange = DevelopmentCurve::getChange($age);
            $change = DevelopmentCurve::calculateChange($baseChange, $assumedAppearances);

            // Quality gap bonus
            $gapBonus = $this->calculateQualityGapBonus($currentOverall, $potential, $age);
            if ($change > 0) {
                $change += $gapBonus;
            }

            $projectedOverall = $currentOverall + $change;

            if ($change > 0) {
                $projectedOverall = min($projectedOverall, $potential);
            }

            $projectedOverall = max(1, min(99, $projectedOverall));

            $projections[] = [
                'season' => $i,
                'age' => $age,
                'overall' => $projectedOverall,
                'status' => PlayerAge::developmentStatus($age),
            ];

            // Use projected value for next iteration
            $currentOverall = $projectedOverall;
        }

        return $projections;
    }

    /**
     * Get the projected change for the next season.
     *
     * @return int The projected change in overall ability
     */
    public function getNextSeasonProjection(GamePlayer $player): int
    {
        $projections = $this->projectDevelopment($player, 1);

        if (empty($projections)) {
            return 0;
        }

        return $projections[0]['overall'] - $player->overall_score;
    }

    /**
     * Apply development changes to a player.
     *
     * Splits the write between game_players (ability column) and the
     * match-state satellite (season_appearances reset). Active-team players
     * always have a satellite row by the time development runs; the silent
     * no-op for missing rows is correct because pool players never accrue
     * appearances anyway.
     */
    public function applyDevelopment(GamePlayer $player, int $newOverall): void
    {
        $player->update([
            'overall_score' => $newOverall,
        ]);

        GamePlayerMatchState::bulkSetValues([$player->id => ['season_appearances' => 0]]);
    }

    /**
     * Recalculate potential for an existing player.
     *
     * Called when market value changes significantly or when
     * we want to update potential estimates based on performance.
     */
    public function recalculatePotential(GamePlayer $player): array
    {
        $marketValueCents = $player->market_value_cents ?? 0;

        return $this->generatePotential($player->age($player->game->current_date), $player->overall_score, $marketValueCents);
    }
}
