<?php

namespace App\Modules\Player\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InjuryService
{
    /**
     * Base injury chance per player per match (percentage).
     */
    private const BASE_INJURY_CHANCE = 1.0;

    /**
     * Base training injury chance per player per matchday (percentage).
     * Applies to all squad members (playing and non-playing).
     */
    private const TRAINING_INJURY_CHANCE = 1.05;

    /**
     * Goalkeepers face far fewer physical duels and run significantly less
     * than outfield players, so their injury risk is much lower.
     */
    private const GK_MATCH_INJURY_MULTIPLIER = 0.3;

    private const GK_TRAINING_INJURY_MULTIPLIER = 0.5;

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
            'positions' => ['MF', 'FW', 'DF', 'GK'],
            'weight' => 30,
        ],
        'Muscle strain' => [
            'weeks' => [1, 2],
            'positions' => ['MF', 'FW', 'DF', 'GK'],
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
            'positions' => ['MF', 'DF', 'FW', 'GK'],
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
     * Training injury type weights — skewed toward minor injuries.
     * Severe injuries (ACL, Achilles, metatarsal) don't happen in training.
     */
    private const TRAINING_INJURY_WEIGHTS = [
        'Muscle fatigue' => 40,
        'Muscle strain' => 30,
        'Calf strain' => 15,
        'Ankle sprain' => 8,
        'Groin strain' => 5,
        'Hamstring tear' => 2,
    ];

    /**
     * Durability multipliers based on player's hidden durability attribute (1-100).
     */
    private const DURABILITY_THRESHOLDS = [
        ['max' => 20, 'multiplier' => 1.8],   // Very injury prone
        ['max' => 40, 'multiplier' => 1.4],   // Injury prone
        ['max' => 60, 'multiplier' => 1.0],   // Average
        ['max' => 80, 'multiplier' => 0.7],   // Resilient
        ['max' => 100, 'multiplier' => 0.4],  // Ironman
    ];

    /**
     * Fitness multipliers for injury risk.
     */
    private const FITNESS_THRESHOLDS = [
        ['max' => 30, 'multiplier' => 2.25],  // Exhausted
        ['max' => 50, 'multiplier' => 1.75],  // Very tired
        ['max' => 70, 'multiplier' => 1.35],  // Tired
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
        $ageMultiplier = $this->getAgeMultiplier($player->age($player->game->current_date));
        $fitnessMultiplier = $this->getFitnessMultiplier($player->fitness);
        $congestionMultiplier = $this->getCongestionMultiplier($lastMatchDate, $currentMatchDate);
        $medicalMultiplier = $this->getMedicalInjuryMultiplier($game);

        // Goalkeepers face much lower injury risk
        $positionMultiplier = $this->getPositionGroup($player->position) === 'GK'
            ? self::GK_MATCH_INJURY_MULTIPLIER
            : 1.0;

        // Calculate final probability
        $finalProbability = $baseProbability
            * $durabilityMultiplier
            * $ageMultiplier
            * $fitnessMultiplier
            * $congestionMultiplier
            * $medicalMultiplier
            * $positionMultiplier;

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
     * Only injury types that include the player's position group are eligible.
     */
    private function selectInjuryType(GamePlayer $player): string
    {
        $positionGroup = $this->getPositionGroup($player->position);
        $weightedTypes = [];

        foreach (self::INJURY_TYPES as $type => $config) {
            if (in_array($positionGroup, $config['positions'])) {
                $weightedTypes[$type] = $config['weight'];
            }
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
        $age = $player->age($player->game->current_date);
        if ($age > PlayerAge::PRIME_END) {
            $baseWeeks = (int) ceil($baseWeeks * 1.2);
        } elseif ($age > PlayerAge::YOUNG_END) {
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
        return match (true) {
            $age <= PlayerAge::ACADEMY_END => 1.2,  // Young, still developing
            $age <= PlayerAge::PRIME_END => 1.0,    // Prime years
            default => 1.4,                          // Veteran
        };
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
            return 1.8; // Back-to-back games
        } elseif ($daysSinceLastMatch <= 3) {
            return 1.4; // Very congested
        } elseif ($daysSinceLastMatch <= 4) {
            return 1.15; // Slightly congested
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
        $tier = $investment->medical_tier ?? 1;

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
        $tier = $investment->medical_tier ?? 1;

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

    // ==========================================
    // Training Injuries
    // ==========================================

    /**
     * Roll for training injuries among squad members.
     * At most one player per team gets injured per matchday.
     *
     * @param  Collection<GamePlayer>  $eligiblePlayers  Non-injured squad members
     * @param  Game|null  $game  Optional game for medical tier effects
     * @return array{player: GamePlayer, type: string, weeks: int}|null
     */
    public function rollTrainingInjuries(Collection $eligiblePlayers, ?Game $game = null): ?array
    {
        foreach ($eligiblePlayers->shuffle() as $player) {
            if ($this->rollForTrainingInjury($player, $game)) {
                return $this->generateTrainingInjury($player, $game);
            }
        }

        return null;
    }

    /**
     * Calculate training injury probability for a player.
     * Same modifiers as match injuries except no congestion multiplier.
     */
    private function calculateTrainingInjuryProbability(GamePlayer $player, ?Game $game = null): float
    {
        $baseProbability = self::TRAINING_INJURY_CHANCE;

        $durabilityMultiplier = $this->getDurabilityMultiplier($player);
        $ageMultiplier = $this->getAgeMultiplier($player->age($player->game->current_date));
        $fitnessMultiplier = $this->getFitnessMultiplier($player->fitness);
        $medicalMultiplier = $this->getMedicalInjuryMultiplier($game);

        $positionMultiplier = $this->getPositionGroup($player->position) === 'GK'
            ? self::GK_TRAINING_INJURY_MULTIPLIER
            : 1.0;

        $finalProbability = $baseProbability
            * $durabilityMultiplier
            * $ageMultiplier
            * $fitnessMultiplier
            * $medicalMultiplier
            * $positionMultiplier;

        return min($finalProbability, 25.0);
    }

    /**
     * Roll whether a player gets injured during training.
     */
    private function rollForTrainingInjury(GamePlayer $player, ?Game $game = null): bool
    {
        $probability = $this->calculateTrainingInjuryProbability($player, $game);

        return $this->percentChance($probability);
    }

    /**
     * Generate a training injury for a player.
     * Training injuries skew toward minor types (no ACL/Achilles/metatarsal).
     *
     * @return array{player: GamePlayer, type: string, weeks: int}
     */
    private function generateTrainingInjury(GamePlayer $player, ?Game $game = null): array
    {
        $weights = $this->getTrainingInjuryWeightsForPosition(
            $this->getPositionGroup($player->position)
        );
        $injuryType = $this->weightedRandomSelect($weights);
        $weeksOut = $this->calculateInjuryDuration($injuryType, $player, $game);

        return [
            'player' => $player,
            'type' => $injuryType,
            'weeks' => $weeksOut,
        ];
    }

    /**
     * Filter training injury weights to only include types matching the player's position.
     */
    private function getTrainingInjuryWeightsForPosition(string $positionGroup): array
    {
        $filtered = [];

        foreach (self::TRAINING_INJURY_WEIGHTS as $type => $weight) {
            if (isset(self::INJURY_TYPES[$type]) && in_array($positionGroup, self::INJURY_TYPES[$type]['positions'])) {
                $filtered[$type] = $weight;
            }
        }

        // Fallback to all training weights if nothing matches (shouldn't happen)
        return $filtered ?: self::TRAINING_INJURY_WEIGHTS;
    }

    /**
     * Build a map of matches missed for injured players in a collection.
     *
     * @return array<string, array{count: int, approx: bool}>  Keyed by player ID
     */
    public static function getMatchesMissedMap(string $gameId, string $teamId, Carbon $referenceDate, Collection $players): array
    {
        $upcomingMatchDates = self::getUpcomingMatchDates($gameId, $teamId, $referenceDate);
        $lastScheduledDate = $upcomingMatchDates->last();

        $map = [];
        foreach ($players as $player) {
            if ($player->isInjured($referenceDate) && $player->injury_until) {
                $map[$player->id] = [
                    'count' => $upcomingMatchDates->filter(fn ($d) => $d->lt($player->injury_until))->count(),
                    'approx' => $lastScheduledDate && $player->injury_until->gt($lastScheduledDate),
                ];
            }
        }

        return $map;
    }

    /**
     * Get matches missed for a single injured player.
     *
     * @return array{count: int, approx: bool}
     */
    public static function getMatchesMissed(string $gameId, string $teamId, Carbon $referenceDate, Carbon $injuryUntil): array
    {
        $upcomingMatchDates = self::getUpcomingMatchDates($gameId, $teamId, $referenceDate);
        $lastScheduledDate = $upcomingMatchDates->last();

        return [
            'count' => $upcomingMatchDates->filter(fn ($d) => $d->lt($injuryUntil))->count(),
            'approx' => $lastScheduledDate && $injuryUntil->gt($lastScheduledDate),
        ];
    }

    private static function getUpcomingMatchDates(string $gameId, string $teamId, Carbon $referenceDate): Collection
    {
        return GameMatch::where('game_id', $gameId)
            ->where('played', false)
            ->where(fn ($q) => $q->where('home_team_id', $teamId)
                                  ->orWhere('away_team_id', $teamId))
            ->where('scheduled_date', '>=', $referenceDate)
            ->orderBy('scheduled_date')
            ->pluck('scheduled_date');
    }
}
