<?php

namespace App\Game\Services;

use App\Models\GamePlayer;
use Illuminate\Support\Collection;

/**
 * Core service handling all player development logic.
 *
 * Responsible for:
 * - Processing seasonal development for players
 * - Calculating age-based development rates
 * - Generating potential for new players
 * - Projecting future ability development
 */
class PlayerDevelopmentService
{
    /**
     * Process seasonal development for all players in a team.
     *
     * @return array Array of player changes for the SeasonDevelopmentProcessed event
     */
    public function processSeasonEndDevelopment(string $gameId, string $teamId): array
    {
        $players = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();

        $changes = [];

        foreach ($players as $player) {
            $change = $this->calculateDevelopment($player);

            if ($change['techChange'] !== 0 || $change['physChange'] !== 0) {
                $changes[] = [
                    'playerId' => $player->id,
                    'techBefore' => $change['techBefore'],
                    'techAfter' => $change['techAfter'],
                    'physBefore' => $change['physBefore'],
                    'physAfter' => $change['physAfter'],
                ];
            }
        }

        return $changes;
    }

    /**
     * Calculate development for a single player.
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

        // Calculate changes
        $techChange = DevelopmentCurve::calculateChange($multipliers['technical'], $hasBonus);
        $physChange = DevelopmentCurve::calculateChange($multipliers['physical'], $hasBonus);

        // Calculate new abilities
        $newTech = $currentTech + $techChange;
        $newPhys = $currentPhys + $physChange;

        // Cap at potential (only for growth, not decline)
        if ($player->potential) {
            if ($techChange > 0) {
                $newTech = min($newTech, $player->potential);
            }
            if ($physChange > 0) {
                $newPhys = min($newPhys, $player->potential);
            }
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
     * Generate potential for a new player based on age and current ability.
     *
     * Young players have higher potential ceilings with more uncertainty.
     * Older players have potential closer to their current ability.
     *
     * @return array{potential: int, low: int, high: int}
     */
    public function generatePotential(int $age, int $currentAbility): array
    {
        // Base potential is always at least current ability
        $baseMin = $currentAbility;

        // Calculate maximum potential based on age
        // Younger players can have much higher potential
        if ($age <= 20) {
            // Young players: potential can be 10-25 points above current
            $potentialRange = rand(10, 25);
            $uncertainty = rand(5, 10); // Higher uncertainty for young players
        } elseif ($age <= 24) {
            // Developing players: potential 5-15 points above current
            $potentialRange = rand(5, 15);
            $uncertainty = rand(4, 7);
        } elseif ($age <= 28) {
            // Peak players: potential 0-5 points above current
            $potentialRange = rand(0, 5);
            $uncertainty = rand(2, 4);
        } else {
            // Older players: potential equals current (no more growth)
            $potentialRange = 0;
            $uncertainty = 2;
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
     * Project player's future ability development.
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

            $techChange = DevelopmentCurve::calculateChange($multipliers['technical'], $hasBonus);
            $physChange = DevelopmentCurve::calculateChange($multipliers['physical'], $hasBonus);

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
     */
    public function initializePlayer(GamePlayer $player): void
    {
        $currentAbility = (int) round(
            ($player->player->technical_ability + $player->player->physical_ability) / 2
        );

        $potentialData = $this->generatePotential($player->age, $currentAbility);

        $player->update([
            'game_technical_ability' => $player->player->technical_ability,
            'game_physical_ability' => $player->player->physical_ability,
            'potential' => $potentialData['potential'],
            'potential_low' => $potentialData['low'],
            'potential_high' => $potentialData['high'],
            'season_appearances' => 0,
        ]);
    }
}
