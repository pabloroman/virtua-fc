<?php

namespace App\Modules\Squad\Services;

use App\Models\GamePlayer;
use App\Modules\Squad\Services\DevelopmentCurve;

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
 * - Players far from their potential develop faster (more room to grow)
 * - Physical abilities decline faster than technical abilities
 */
class PlayerDevelopmentService
{
    /**
     * Calculate development for a single player.
     *
     * Development is influenced by:
     * - Age (young players grow, veterans decline)
     * - Playing time (starters develop faster)
     * - Quality gap (players far from potential develop faster)
     * - Potential cap (players can't exceed their ceiling)
     *
     * @return array{
     *     techBefore: int,
     *     techAfter: int,
     *     techChange: int,
     *     physBefore: int,
     *     physAfter: int,
     *     physChange: int
     * }
     */
    public function calculateDevelopment(GamePlayer $player): array
    {
        $age = $player->age;
        $multipliers = DevelopmentCurve::getMultipliers($age);
        $hasBonus = DevelopmentCurve::qualifiesForBonus($player->season_appearances);

        // Get current abilities
        $currentTech = $player->current_technical_ability;
        $currentPhys = $player->current_physical_ability;
        $currentOverall = (int) round(($currentTech + $currentPhys) / 2);
        $potential = $player->potential ?? 99;

        // Calculate quality gap bonus for growing players
        // Players far from their potential develop faster (more room to grow)
        $qualityGapBonus = $this->calculateQualityGapBonus($currentOverall, $potential, $age);

        // Calculate base changes
        $baseTechChange = DevelopmentCurve::calculateChange($multipliers['technical'], $hasBonus);
        $basePhysChange = DevelopmentCurve::calculateChange($multipliers['physical'], $hasBonus);

        // Apply quality gap bonus only to growth (not decline)
        $techChange = $baseTechChange > 0
            ? (int) round($baseTechChange * $qualityGapBonus)
            : $baseTechChange;
        $physChange = $basePhysChange > 0
            ? (int) round($basePhysChange * $qualityGapBonus)
            : $basePhysChange;

        // Calculate new abilities
        $newTech = $currentTech + $techChange;
        $newPhys = $currentPhys + $physChange;

        // Cap at potential (only for growth, not decline)
        if ($techChange > 0) {
            $newTech = min($newTech, $potential);
        }
        if ($physChange > 0) {
            $newPhys = min($newPhys, $potential);
        }

        // Ensure abilities stay within valid range (1-99)
        $newTech = max(1, min(99, $newTech));
        $newPhys = max(1, min(99, $newPhys));

        return [
            'techBefore' => $currentTech,
            'techAfter' => $newTech,
            'techChange' => $newTech - $currentTech,
            'physBefore' => $currentPhys,
            'physAfter' => $newPhys,
            'physChange' => $newPhys - $currentPhys,
        ];
    }

    /**
     * Calculate bonus multiplier for players far from their potential.
     *
     * Young players with a big gap between current ability and potential
     * develop faster - they have more room to grow and often receive
     * better coaching/opportunities at top clubs.
     *
     * @return float Multiplier (1.0 to 1.5)
     */
    private function calculateQualityGapBonus(int $currentAbility, int $potential, int $age): float
    {
        // Only applies to growing players (under 28)
        if ($age >= 28) {
            return 1.0;
        }

        $gap = $potential - $currentAbility;

        if ($gap <= 5) {
            return 1.0; // Already close to potential
        }

        // Gap bonus: up to 50% faster development for players with 20+ point gap
        // 10 point gap = 25% bonus, 20 point gap = 50% bonus
        return min(1.5, 1.0 + ($gap / 40));
    }

    /**
     * Generate potential for a new player based on age, current ability, and market value.
     *
     * Market value is a key indicator of potential:
     * - Young players with high market value have PROVEN their potential at top level
     * - Veterans with exceptional market value have PROVEN their quality ceiling
     *
     * The logic aligns with the ability calculation:
     * - Young players are "capped" in current ability but can have very high potential
     * - Veterans are "boosted" in current ability and their potential reflects proven peak
     *
     * @param int $age Player's current age
     * @param int $currentAbility Player's current overall ability
     * @param int $marketValueCents Player's market value in cents (e.g., €15M = 1500000000)
     * @return array{potential: int, low: int, high: int}
     */
    public function generatePotential(int $age, int $currentAbility, int $marketValueCents = 0): array
    {
        // Get bonus/adjustment based on market value relative to age
        $valueBonus = $this->getValuePotentialBonus($age, $marketValueCents);

        // Calculate potential range based on age
        if ($age <= 20) {
            // Young players: high potential ceiling
            // Base range 8-20, plus value bonus for proven youngsters
            $basePotentialRange = rand(8, 20);
            $potentialRange = $basePotentialRange + $valueBonus;
            $uncertainty = rand(5, 10); // Higher uncertainty for young players
        } elseif ($age <= 24) {
            // Developing players: moderate potential
            // Base range 4-12, plus reduced value bonus
            $basePotentialRange = rand(4, 12);
            $potentialRange = $basePotentialRange + (int) ($valueBonus * 0.6);
            $uncertainty = rand(4, 7);
        } elseif ($age <= 28) {
            // Peak players: small potential margin
            // Base range 0-5, plus small value bonus
            $basePotentialRange = rand(0, 5);
            $potentialRange = $basePotentialRange + (int) ($valueBonus * 0.3);
            $uncertainty = rand(2, 4);
        } else {
            // Veterans (29+): potential reflects proven quality
            // Exceptional market value = they've proven their ceiling
            $potentialRange = $this->getVeteranPotentialBonus($age, $currentAbility, $marketValueCents);
            $uncertainty = 2; // Low uncertainty - we know what they can do
        }

        // True potential (hidden)
        $truePotential = min(99, $currentAbility + $potentialRange);

        // Scouted range (visible) - adds uncertainty
        $low = max($currentAbility, $truePotential - $uncertainty);
        $high = min(99, $truePotential + $uncertainty);

        return [
            'potential' => $truePotential,
            'low' => $low,
            'high' => $high,
        ];
    }

    /**
     * Calculate potential bonus based on market value relative to age.
     *
     * Young players with exceptional market value have PROVEN their potential
     * by performing at the highest level. A €120M 17-year-old has demonstrated
     * they belong with the elite.
     *
     * @return int Bonus points to add to potential range (0-10)
     */
    private function getValuePotentialBonus(int $age, int $marketValueCents): int
    {
        // No bonus for veterans (handled separately)
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

        // Higher ratio = more proven potential
        return match (true) {
            $valueRatio >= 100 => 10, // €120M 17yo (240x typical) = elite potential
            $valueRatio >= 50 => 8,   // €50M 17yo
            $valueRatio >= 20 => 6,   // €40M 19yo
            $valueRatio >= 10 => 4,   // €20M 19yo
            $valueRatio >= 5 => 2,    // €10M 17yo
            default => 0,
        };
    }

    /**
     * Calculate potential adjustment for veteran players.
     *
     * Veterans with exceptional market value have PROVEN their quality.
     * Their potential should reflect their demonstrated ceiling.
     *
     * A €15M 36-year-old Lewandowski has proven he can perform at 90+ level.
     *
     * @return int Points to add to current ability for potential
     */
    private function getVeteranPotentialBonus(int $age, int $currentAbility, int $marketValueCents): int
    {
        // Typical market value for veterans
        $typicalValueForAge = match (true) {
            $age <= 31 => 800_000_000,   // €8M
            $age <= 33 => 400_000_000,   // €4M
            $age <= 35 => 200_000_000,   // €2M
            default => 100_000_000,       // €1M
        };

        $valueRatio = $marketValueCents / max(1, $typicalValueForAge);

        // Exceptional veterans have proven their quality ceiling
        // Their potential reflects what they've already achieved
        return match (true) {
            $valueRatio >= 10 => 8,  // 10x typical = proven world class (Lewandowski, Modric)
            $valueRatio >= 5 => 5,   // 5x typical = proven high quality
            $valueRatio >= 3 => 3,   // 3x typical = above average veteran
            $valueRatio >= 2 => 1,   // 2x typical = solid professional
            default => 0,            // Typical veteran = current ability is their ceiling
        };
    }

    /**
     * Project player's future ability development.
     *
     * Projections account for:
     * - Age-based development curves
     * - Quality gap bonus (players far from potential develop faster)
     * - Potential ceiling (can't exceed potential)
     *
     * @param int $seasons Number of seasons to project
     * @return array Array of projections per season
     */
    public function projectDevelopment(GamePlayer $player, int $seasons = 3): array
    {
        $projections = [];
        $currentTech = $player->current_technical_ability;
        $currentPhys = $player->current_physical_ability;
        $currentAge = $player->age;
        $potential = $player->potential ?? 99;

        // Assume the player will get starter bonus (optimistic projection)
        $hasBonus = true;

        for ($i = 1; $i <= $seasons; $i++) {
            $age = $currentAge + $i;
            $multipliers = DevelopmentCurve::getMultipliers($age);
            $currentOverall = (int) round(($currentTech + $currentPhys) / 2);

            // Calculate quality gap bonus
            $qualityGapBonus = $this->calculateQualityGapBonus($currentOverall, $potential, $age);

            $baseTechChange = DevelopmentCurve::calculateChange($multipliers['technical'], $hasBonus);
            $basePhysChange = DevelopmentCurve::calculateChange($multipliers['physical'], $hasBonus);

            // Apply quality gap bonus only to growth
            $techChange = $baseTechChange > 0
                ? (int) round($baseTechChange * $qualityGapBonus)
                : $baseTechChange;
            $physChange = $basePhysChange > 0
                ? (int) round($basePhysChange * $qualityGapBonus)
                : $basePhysChange;

            // Apply changes
            $projectedTech = $currentTech + $techChange;
            $projectedPhys = $currentPhys + $physChange;

            // Cap at potential for growth
            if ($techChange > 0) {
                $projectedTech = min($projectedTech, $potential);
            }
            if ($physChange > 0) {
                $projectedPhys = min($projectedPhys, $potential);
            }

            // Ensure valid range
            $projectedTech = max(1, min(99, $projectedTech));
            $projectedPhys = max(1, min(99, $projectedPhys));

            $projections[] = [
                'season' => $i,
                'age' => $age,
                'technical' => $projectedTech,
                'physical' => $projectedPhys,
                'overall' => (int) round(($projectedTech + $projectedPhys) / 2),
                'status' => DevelopmentCurve::getStatus($age),
            ];

            // Use projected values for next iteration
            $currentTech = $projectedTech;
            $currentPhys = $projectedPhys;
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

        $currentOverall = (int) round(
            ($player->current_technical_ability + $player->current_physical_ability) / 2
        );

        return $projections[0]['overall'] - $currentOverall;
    }

    /**
     * Apply development changes to a player.
     */
    public function applyDevelopment(GamePlayer $player, int $newTech, int $newPhys): void
    {
        $player->update([
            'game_technical_ability' => $newTech,
            'game_physical_ability' => $newPhys,
            'season_appearances' => 0, // Reset for new season
        ]);
    }

    /**
     * Initialize development attributes for a new game player.
     *
     * Uses market value to influence potential calculation:
     * - High-value youngsters have proven potential
     * - High-value veterans have proven their ceiling
     */
    public function initializePlayer(GamePlayer $player): void
    {
        $currentAbility = (int) round(
            ($player->player->technical_ability + $player->player->physical_ability) / 2
        );

        // Pass market value to influence potential calculation
        $marketValueCents = $player->market_value_cents ?? 0;
        $potentialData = $this->generatePotential($player->age, $currentAbility, $marketValueCents);

        $player->update([
            'game_technical_ability' => $player->player->technical_ability,
            'game_physical_ability' => $player->player->physical_ability,
            'potential' => $potentialData['potential'],
            'potential_low' => $potentialData['low'],
            'potential_high' => $potentialData['high'],
            'season_appearances' => 0,
        ]);
    }

    /**
     * Recalculate potential for an existing player.
     *
     * Called when market value changes significantly or when
     * we want to update potential estimates based on performance.
     */
    public function recalculatePotential(GamePlayer $player): array
    {
        $currentAbility = (int) round(
            ($player->current_technical_ability + $player->current_physical_ability) / 2
        );

        $marketValueCents = $player->market_value_cents ?? 0;

        return $this->generatePotential($player->age, $currentAbility, $marketValueCents);
    }

}
