<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;

class InjuryService
{
    /**
     * Base injury chance per player per match (percentage).
     */
    private const BASE_INJURY_CHANCE = 4.0;

    /**
     * Medical tier multipliers for injury prevention.
     * Higher tier = lower injury chance.
     */
    private const MEDICAL_INJURY_MULTIPLIER = [
        0 => 1.3,   // No medical staff - higher risk
        1 => 1.0,   // Basic - baseline
        2 => 0.85,  // Good - 15% reduction
        3 => 0.70,  // Excellent - 30% reduction
        4 => 0.55,  // World-class - 45% reduction
    ];

    /**
     * Medical tier multipliers for recovery time.
     * Higher tier = faster recovery.
     */
    private const MEDICAL_RECOVERY_MULTIPLIER = [
        0 => 1.2,   // No medical staff - slower recovery
        1 => 1.0,   // Basic - baseline
        2 => 0.9,   // Good - 10% faster
        3 => 0.8,   // Excellent - 20% faster
        4 => 0.7,   // World-class - 30% faster
    ];

    /**
     * Injury types with duration ranges (in weeks) and position affinities.
     * Position affinities: GK = Goalkeeper, DF = Defender, MF = Midfielder, FW = Forward
     *
     * Distribution: Minor injuries are common, severe injuries are rare.
     * Weights are inversely proportional to recovery time.
     */
    /**
     * Map of English injury type keys to translation keys.
     */
    public const INJURY_TRANSLATION_MAP = [
        'Muscle fatigue' => 'squad.injury_muscle_fatigue',
        'Muscle strain' => 'squad.injury_muscle_strain',
        'Calf strain' => 'squad.injury_calf_strain',
        'Ankle sprain' => 'squad.injury_ankle_sprain',
        'Groin strain' => 'squad.injury_groin_strain',
        'Hamstring tear' => 'squad.injury_hamstring_tear',
        'Knee contusion' => 'squad.injury_knee_contusion',
        'Metatarsal fracture' => 'squad.injury_metatarsal_fracture',
        'ACL tear' => 'squad.injury_acl_tear',
        'Achilles rupture' => 'squad.injury_achilles_rupture',
    ];

    private const INJURY_TYPES = [
        // Minor (1-2 weeks) - Very common
        'Muscle fatigue' => [
            'weeks' => [1, 1],
            'positions' => ['MF', 'FW', 'DF'],
            'weight' => 30,
        ],
        'Muscle strain' => [
            'weeks' => [1, 2],
            'positions' => ['MF', 'FW', 'DF'],
            'weight' => 25,
        ],
        // Medium (2-4 weeks) - Common
        'Calf strain' => [
            'weeks' => [2, 3],
            'positions' => ['MF', 'FW'],
            'weight' => 18,
        ],
        'Ankle sprain' => [
            'weeks' => [2, 4],
            'positions' => ['MF', 'DF', 'FW'],
            'weight' => 16,
        ],
        'Groin strain' => [
            'weeks' => [2, 4],
            'positions' => ['FW', 'MF', 'DF'],
            'weight' => 14,
        ],
        // Serious (3-6 weeks) - Less common
        'Hamstring tear' => [
            'weeks' => [3, 6],
            'positions' => ['FW', 'MF'],
            'weight' => 10,
        ],
        'Knee contusion' => [
            'weeks' => [3, 5],
            'positions' => ['DF', 'MF', 'GK'],
            'weight' => 8,
        ],
        // Long-term (6-12 weeks) - Uncommon
        'Metatarsal fracture' => [
            'weeks' => [8, 12],
            'positions' => ['MF', 'FW', 'DF'],
            'weight' => 4,
        ],
        // Severe (20+ weeks) - Rare
        'ACL tear' => [
            'weeks' => [24, 36],
            'positions' => ['DF', 'MF', 'FW'],
            'weight' => 2,
        ],
        'Achilles rupture' => [
            'weeks' => [20, 28],
            'positions' => ['FW', 'MF', 'DF'],
            'weight' => 1,
        ],
    ];

    /**
     * Durability multipliers based on player's hidden durability attribute (1-100).
     */
    private const DURABILITY_THRESHOLDS = [
        ['max' => 20, 'multiplier' => 2.0],   // Very injury prone
        ['max' => 40, 'multiplier' => 1.5],   // Injury prone
        ['max' => 60, 'multiplier' => 1.0],   // Average
        ['max' => 80, 'multiplier' => 0.7],   // Resilient
        ['max' => 100, 'multiplier' => 0.4],  // Ironman
    ];

    /**
     * Age multipliers for injury risk.
     */
    private const AGE_THRESHOLDS = [
        ['max' => 20, 'multiplier' => 1.3],   // Young, still developing
        ['max' => 29, 'multiplier' => 1.0],   // Prime years
        ['max' => 32, 'multiplier' => 1.2],   // Early decline
        ['max' => 99, 'multiplier' => 1.5],   // Veteran
    ];

    /**
     * Fitness multipliers for injury risk.
     */
    private const FITNESS_THRESHOLDS = [
        ['max' => 30, 'multiplier' => 2.5],   // Exhausted
        ['max' => 50, 'multiplier' => 2.0],   // Very tired
        ['max' => 70, 'multiplier' => 1.5],   // Tired
        ['max' => 85, 'multiplier' => 1.0],   // Normal
        ['max' => 100, 'multiplier' => 0.8],  // Fresh
    ];

    /**
     * Calculate the injury probability for a player in a given match context.
     *
     * @param  Carbon|null  $lastMatchDate  Date of player's last match (for congestion)
     * @param  Game|null  $game  Optional game to get medical tier
     * @return float Injury probability as percentage (0-100)
     */
    public function calculateInjuryProbability(GamePlayer $player, ?Carbon $lastMatchDate = null, ?Carbon $currentMatchDate = null, ?Game $game = null): float
    {
        $baseProbability = self::BASE_INJURY_CHANCE;

        // Get multipliers
        $durabilityMultiplier = $this->getDurabilityMultiplier($player);
        $ageMultiplier = $this->getAgeMultiplier($player->age);
        $fitnessMultiplier = $this->getFitnessMultiplier($player->fitness);
        $congestionMultiplier = $this->getCongestionMultiplier($lastMatchDate, $currentMatchDate);
        $medicalMultiplier = $this->getMedicalInjuryMultiplier($game);

        // Calculate final probability
        $finalProbability = $baseProbability
            * $durabilityMultiplier
            * $ageMultiplier
            * $fitnessMultiplier
            * $congestionMultiplier
            * $medicalMultiplier;

        // Cap at reasonable maximum
        return min($finalProbability, 35.0);
    }

    /**
     * Determine if a player gets injured based on their calculated probability.
     *
     * @param  Game|null  $game  Optional game to get medical tier
     */
    public function rollForInjury(GamePlayer $player, ?Carbon $lastMatchDate = null, ?Carbon $currentMatchDate = null, ?Game $game = null): bool
    {
        $probability = $this->calculateInjuryProbability($player, $lastMatchDate, $currentMatchDate, $game);

        return $this->percentChance($probability);
    }

    /**
     * Generate an injury for a player, returning injury details.
     *
     * @param  Game|null  $game  Optional game to get medical tier
     * @return array{type: string, weeks: int, minute: int}
     */
    public function generateInjury(GamePlayer $player, ?Game $game = null): array
    {
        $injuryType = $this->selectInjuryType($player);
        $weeksOut = $this->calculateInjuryDuration($injuryType, $player, $game);
        $minute = rand(1, 85);

        return [
            'type' => $injuryType,
            'weeks' => $weeksOut,
            'minute' => $minute,
        ];
    }

    /**
     * Select an injury type based on player's position.
     */
    private function selectInjuryType(GamePlayer $player): string
    {
        $positionGroup = $this->getPositionGroup($player->position);
        $weightedTypes = [];

        foreach (self::INJURY_TYPES as $type => $config) {
            $weight = $config['weight'];

            // Increase weight if this injury is common for the player's position
            if (in_array($positionGroup, $config['positions'])) {
                $weight *= 2;
            }

            $weightedTypes[$type] = $weight;
        }

        return $this->weightedRandomSelect($weightedTypes);
    }

    /**
     * Calculate injury duration, potentially affected by player age and medical tier.
     *
     * @param  Game|null  $game  Optional game to get medical tier
     * @return int Weeks out
     */
    private function calculateInjuryDuration(string $injuryType, GamePlayer $player, ?Game $game = null): int
    {
        $config = self::INJURY_TYPES[$injuryType];
        [$minWeeks, $maxWeeks] = $config['weeks'];

        $baseWeeks = rand($minWeeks, $maxWeeks);

        // Older players take slightly longer to recover
        if ($player->age >= 32) {
            $baseWeeks = (int) ceil($baseWeeks * 1.2);
        } elseif ($player->age >= 30) {
            $baseWeeks = (int) ceil($baseWeeks * 1.1);
        }

        // Apply medical tier recovery multiplier
        $medicalMultiplier = $this->getMedicalRecoveryMultiplier($game);
        $baseWeeks = (int) ceil($baseWeeks * $medicalMultiplier);

        // Minimum 1 week for any injury
        return max(1, $baseWeeks);
    }

    /**
     * Get durability multiplier from player's hidden durability attribute.
     */
    private function getDurabilityMultiplier(GamePlayer $player): float
    {
        $durability = $player->durability ?? 50;

        foreach (self::DURABILITY_THRESHOLDS as $threshold) {
            if ($durability <= $threshold['max']) {
                return $threshold['multiplier'];
            }
        }

        return 1.0;
    }

    /**
     * Get age multiplier for injury risk.
     */
    private function getAgeMultiplier(int $age): float
    {
        foreach (self::AGE_THRESHOLDS as $threshold) {
            if ($age <= $threshold['max']) {
                return $threshold['multiplier'];
            }
        }

        return 1.0;
    }

    /**
     * Get fitness multiplier for injury risk.
     */
    private function getFitnessMultiplier(int $fitness): float
    {
        foreach (self::FITNESS_THRESHOLDS as $threshold) {
            if ($fitness <= $threshold['max']) {
                return $threshold['multiplier'];
            }
        }

        return 1.0;
    }

    /**
     * Get congestion multiplier based on days since last match.
     */
    private function getCongestionMultiplier(?Carbon $lastMatchDate, ?Carbon $currentMatchDate): float
    {
        if (! $lastMatchDate || ! $currentMatchDate) {
            return 1.0;
        }

        $daysSinceLastMatch = $lastMatchDate->diffInDays($currentMatchDate);

        if ($daysSinceLastMatch <= 2) {
            return 2.0; // Back-to-back games
        } elseif ($daysSinceLastMatch <= 3) {
            return 1.5; // Very congested
        } elseif ($daysSinceLastMatch <= 4) {
            return 1.2; // Slightly congested
        }

        return 1.0; // Normal rest
    }

    /**
     * Get medical tier multiplier for injury prevention.
     */
    private function getMedicalInjuryMultiplier(?Game $game): float
    {
        if (! $game) {
            return 1.0;
        }

        $investment = $game->currentInvestment;
        $tier = $investment?->medical_tier ?? 1;

        return self::MEDICAL_INJURY_MULTIPLIER[$tier] ?? 1.0;
    }

    /**
     * Get medical tier multiplier for recovery time.
     */
    private function getMedicalRecoveryMultiplier(?Game $game): float
    {
        if (! $game) {
            return 1.0;
        }

        $investment = $game->currentInvestment;
        $tier = $investment?->medical_tier ?? 1;

        return self::MEDICAL_RECOVERY_MULTIPLIER[$tier] ?? 1.0;
    }

    /**
     * Map detailed position to position group.
     */
    private function getPositionGroup(string $position): string
    {
        return match ($position) {
            'Goalkeeper' => 'GK',
            'Centre-Back', 'Left-Back', 'Right-Back' => 'DF',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield' => 'MF',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker' => 'FW',
            default => 'MF',
        };
    }

    /**
     * Check if a percentage chance succeeds.
     */
    private function percentChance(float $percent): bool
    {
        return (mt_rand(0, 10000) / 100) < $percent;
    }

    /**
     * Select a random item based on weights.
     */
    private function weightedRandomSelect(array $weightedItems): string
    {
        $totalWeight = array_sum($weightedItems);
        $random = mt_rand(1, $totalWeight);

        foreach ($weightedItems as $item => $weight) {
            $random -= $weight;
            if ($random <= 0) {
                return $item;
            }
        }

        return array_key_first($weightedItems);
    }

    /**
     * Generate a random durability value with bell-curve distribution.
     * Most players will be average (40-60), fewer at extremes.
     *
     * @return int Durability value 1-100
     */
    public static function generateDurability(): int
    {
        // Use sum of multiple random numbers for bell curve
        $sum = 0;
        for ($i = 0; $i < 4; $i++) {
            $sum += mt_rand(1, 25);
        }

        // This gives a range of 4-100 with most values around 50
        return max(1, min(100, $sum));
    }

    /**
     * Get a human-readable durability description (for debugging/admin).
     */
    public static function getDurabilityLabel(int $durability): string
    {
        if ($durability <= 20) {
            return 'Very Injury Prone';
        }
        if ($durability <= 40) {
            return 'Injury Prone';
        }
        if ($durability <= 60) {
            return 'Average';
        }
        if ($durability <= 80) {
            return 'Resilient';
        }

        return 'Ironman';
    }
}
