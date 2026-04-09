<?php

namespace App\Modules\Player\Services;

use App\Models\GamePlayer;
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
 * - Growth requires playing time — bench players stagnate
 * - Physical abilities decline faster than technical abilities
 */
class PlayerDevelopmentService
{
    /**
     * Calculate development for a single player.
     *
     * Development is influenced by:
     * - Age (young players grow, veterans decline)
     * - Playing time (must play to grow; no appearances = stagnation)
     * - Quality gap (young players far from potential get +1 bonus)
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
    public function calculateDevelopment(GamePlayer $player, ?int $precomputedAge = null): array
    {
        $age = $precomputedAge ?? $player->age($player->game->current_date);
        $changes = DevelopmentCurve::getChanges($age);
        $appearances = $player->season_appearances;

        $currentTech = $player->current_technical_ability;
        $currentPhys = $player->current_physical_ability;
        $currentOverall = (int) round(($currentTech + $currentPhys) / 2);
        $potential = $player->potential ?? 99;

        // Base changes from curve + playing time
        $techChange = DevelopmentCurve::calculateChange($changes['technical'], $appearances);
        $physChange = DevelopmentCurve::calculateChange($changes['physical'], $appearances);

        // Quality gap: flat +1 bonus for young players far from potential
        $gapBonus = $this->calculateQualityGapBonus($currentOverall, $potential, $age);
        if ($techChange > 0) {
            $techChange += $gapBonus;
        }
        if ($physChange > 0) {
            $physChange += $gapBonus;
        }

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
     * Flat bonus for young players far from their potential.
     *
     * Returns +1 for young players with a significant gap to potential,
     * representing accelerated development from coaching and opportunity.
     *
     * @return int Bonus points (0 or 1)
     */
    private function calculateQualityGapBonus(int $currentAbility, int $potential, int $age): int
    {
        // Only for young developing players (under 23)
        if ($age >= 23) {
            return 0;
        }

        $gap = $potential - $currentAbility;

        // +1 bonus for high-potential youngsters with significant room to grow
        if ($gap >= 15) {
            return 1;
        }

        return 0;
    }

    /**
     * Generate potential for a new player based on age, current ability, and market value.
     *
     * Market value is a key indicator of potential:
     * - Young players with high market value have PROVEN their potential at top level
     * - Veterans with exceptional market value have PROVEN their quality ceiling
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
            // Peak players: small potential margin
            $basePotentialRange = rand(0, 5);
            $potentialRange = $basePotentialRange + (int) ($valueBonus * 0.3);
            $uncertainty = rand(2, 4);
        } else {
            // Veterans: potential reflects proven quality
            $potentialRange = $this->getVeteranPotentialBonus($age, $currentAbility, $marketValueCents);
            $uncertainty = 2; // Low uncertainty — we know what they can do
        }

        // True potential (hidden from user)
        $truePotential = min(99, $currentAbility + $potentialRange);

        // Scouted range (visible to user) — adds uncertainty around true value
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
     * @return int Bonus points to add to potential range (0-10)
     */
    private function getValuePotentialBonus(int $age, int $marketValueCents): int
    {
        // No bonus for veterans (handled separately in getVeteranPotentialBonus)
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
            $valueRatio >= 100 => 10, // e.g. €120M 17yo (240x typical) = elite potential
            $valueRatio >= 50 => 8,
            $valueRatio >= 20 => 6,
            $valueRatio >= 10 => 4,
            $valueRatio >= 5 => 2,
            default => 0,
        };
    }

    /**
     * Calculate potential adjustment for veteran players.
     *
     * Veterans with exceptional market value have proven their quality ceiling.
     *
     * @return int Points to add to current ability for potential
     */
    private function getVeteranPotentialBonus(int $age, int $currentAbility, int $marketValueCents): int
    {
        // Typical market value for veterans
        $typicalValueForAge = match (true) {
            $age <= 33 => 800_000_000,   // €8M
            $age <= 35 => 400_000_000,   // €4M
            $age <= 37 => 200_000_000,   // €2M
            default => 100_000_000,       // €1M
        };

        $valueRatio = $marketValueCents / max(1, $typicalValueForAge);

        return match (true) {
            $valueRatio >= 10 => 8,  // 10x typical = proven world class (Lewandowski, Modric)
            $valueRatio >= 5 => 5,   // 5x typical = proven high quality
            $valueRatio >= 3 => 3,
            $valueRatio >= 2 => 1,
            default => 0,            // Typical veteran = current ability is their ceiling
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
        $currentTech = $player->current_technical_ability;
        $currentPhys = $player->current_physical_ability;
        $currentAge = $player->age($player->game->current_date);
        $potential = $player->potential ?? 99;

        // Assume regular starter for optimistic projection
        $assumedAppearances = DevelopmentCurve::FULL_BONUS_APPEARANCES;

        for ($i = 1; $i <= $seasons; $i++) {
            $age = $currentAge + $i;
            $changes = DevelopmentCurve::getChanges($age);
            $currentOverall = (int) round(($currentTech + $currentPhys) / 2);

            $techChange = DevelopmentCurve::calculateChange($changes['technical'], $assumedAppearances);
            $physChange = DevelopmentCurve::calculateChange($changes['physical'], $assumedAppearances);

            // Quality gap bonus
            $gapBonus = $this->calculateQualityGapBonus($currentOverall, $potential, $age);
            if ($techChange > 0) {
                $techChange += $gapBonus;
            }
            if ($physChange > 0) {
                $physChange += $gapBonus;
            }

            $projectedTech = $currentTech + $techChange;
            $projectedPhys = $currentPhys + $physChange;

            if ($techChange > 0) {
                $projectedTech = min($projectedTech, $potential);
            }
            if ($physChange > 0) {
                $projectedPhys = min($projectedPhys, $potential);
            }

            $projectedTech = max(1, min(99, $projectedTech));
            $projectedPhys = max(1, min(99, $projectedPhys));

            $projections[] = [
                'season' => $i,
                'age' => $age,
                'technical' => $projectedTech,
                'physical' => $projectedPhys,
                'overall' => (int) round(($projectedTech + $projectedPhys) / 2),
                'status' => PlayerAge::developmentStatus($age),
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

        return $this->generatePotential($player->age($player->game->current_date), $currentAbility, $marketValueCents);
    }
}
