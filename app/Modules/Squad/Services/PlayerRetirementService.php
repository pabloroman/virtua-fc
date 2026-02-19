<?php

namespace App\Modules\Squad\Services;

use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;
use App\Modules\Squad\Services\PlayerGeneratorService;

class PlayerRetirementService
{
    /**
     * Base retirement probability by age for outfield players.
     * Probability of announcing retirement at this age.
     */
    private const OUTFIELD_BASE_PROBABILITY = [
        33 => 0.05,
        34 => 0.15,
        35 => 0.30,
        36 => 0.50,
        37 => 0.70,
        38 => 0.85,
        39 => 0.95,
    ];

    /**
     * Base retirement probability by age for goalkeepers.
     * Goalkeepers have longer careers.
     */
    private const GOALKEEPER_BASE_PROBABILITY = [
        35 => 0.05,
        36 => 0.10,
        37 => 0.20,
        38 => 0.35,
        39 => 0.55,
        40 => 0.75,
        41 => 0.90,
    ];

    /**
     * Minimum age at which retirement can be considered.
     */
    private const MIN_RETIREMENT_AGE_OUTFIELD = 33;
    private const MIN_RETIREMENT_AGE_GOALKEEPER = 35;

    /**
     * Age at which retirement is guaranteed.
     */
    private const MAX_CAREER_AGE_OUTFIELD = 40;
    private const MAX_CAREER_AGE_GOALKEEPER = 42;

    /**
     * Starter threshold: appearances that count as a regular starter.
     */
    private const STARTER_APPEARANCES = 25;

    /**
     * Low appearances threshold: player barely plays.
     */
    private const LOW_APPEARANCES = 5;

    public function __construct(
        private readonly PlayerGeneratorService $playerGenerator,
    ) {}

    /**
     * Evaluate whether a player decides to announce retirement.
     *
     * Takes into account:
     * - Age (primary factor via base probability curve)
     * - Fitness (lower fitness → more likely to retire)
     * - Season appearances (starters delay retirement, bench warmers accelerate)
     * - Current ability (elite players tend to play longer)
     * - Position (goalkeepers have longer careers)
     */
    public function shouldRetire(GamePlayer $player): bool
    {
        $age = $player->age;
        $isGoalkeeper = $player->position === 'Goalkeeper';

        $minAge = $isGoalkeeper ? self::MIN_RETIREMENT_AGE_GOALKEEPER : self::MIN_RETIREMENT_AGE_OUTFIELD;
        $maxAge = $isGoalkeeper ? self::MAX_CAREER_AGE_GOALKEEPER : self::MAX_CAREER_AGE_OUTFIELD;

        // Too young to retire
        if ($age < $minAge) {
            return false;
        }

        // Mandatory retirement at max age
        if ($age >= $maxAge) {
            return true;
        }

        $baseProbability = $this->getBaseProbability($age, $isGoalkeeper);
        $fitnessFactor = $this->getFitnessFactor($player->fitness);
        $appearanceFactor = $this->getAppearanceFactor($player->season_appearances);
        $abilityFactor = $this->getAbilityFactor($player);

        $finalProbability = $baseProbability * $fitnessFactor * $appearanceFactor * $abilityFactor;

        // Clamp to 0.0 - 1.0
        $finalProbability = max(0.0, min(1.0, $finalProbability));

        return (mt_rand(1, 1000) / 1000) <= $finalProbability;
    }

    /**
     * Generate a replacement player for a retiring player on an AI team.
     *
     * The replacement has:
     * - Same position
     * - Younger age (22-28)
     * - Slightly lower ability (70-90% of retiring player)
     * - 3-year contract
     */
    public function generateReplacementPlayer(Game $game, GamePlayer $retiringPlayer, string $newSeason): GamePlayer
    {
        $retiringAbility = (int) round(
            ($retiringPlayer->current_technical_ability + $retiringPlayer->current_physical_ability) / 2
        );

        // Replacement ability: 70-90% of retiring player
        $abilityFraction = (mt_rand(70, 90) / 100);
        $replacementAbility = max(35, (int) round($retiringAbility * $abilityFraction));

        // Split ability between technical and physical with some variance
        $techBias = mt_rand(-5, 5);
        $technical = max(30, min(95, $replacementAbility + $techBias));
        $physical = max(30, min(95, $replacementAbility - $techBias));

        $seasonYear = (int) $newSeason;
        $age = mt_rand(22, 28);
        $dateOfBirth = Carbon::createFromDate($seasonYear - $age, mt_rand(1, 12), mt_rand(1, 28));

        return $this->playerGenerator->create($game, new GeneratedPlayerData(
            teamId: $retiringPlayer->team_id,
            position: $retiringPlayer->position,
            technical: $technical,
            physical: $physical,
            dateOfBirth: $dateOfBirth,
            contractYears: 3,
        ));
    }

    /**
     * Get base retirement probability for an age.
     */
    private function getBaseProbability(int $age, bool $isGoalkeeper): float
    {
        $table = $isGoalkeeper ? self::GOALKEEPER_BASE_PROBABILITY : self::OUTFIELD_BASE_PROBABILITY;

        if (isset($table[$age])) {
            return $table[$age];
        }

        // For ages beyond the table, use 0.99
        $maxTableAge = max(array_keys($table));
        if ($age > $maxTableAge) {
            return 0.99;
        }

        return 0.0;
    }

    /**
     * Fitness factor: unfit players are more likely to retire.
     *
     * @return float Multiplier (0.7 to 1.4)
     */
    private function getFitnessFactor(int $fitness): float
    {
        return match (true) {
            $fitness >= 85 => 0.7,   // Fit players delay retirement
            $fitness >= 70 => 0.9,
            $fitness >= 60 => 1.1,
            default => 1.4,          // Unfit players accelerate retirement
        };
    }

    /**
     * Appearance factor: starters delay retirement, unused players accelerate.
     *
     * @return float Multiplier (0.7 to 1.5)
     */
    private function getAppearanceFactor(int $seasonAppearances): float
    {
        return match (true) {
            $seasonAppearances >= self::STARTER_APPEARANCES => 0.7,  // Regular starter
            $seasonAppearances >= 15 => 0.85,                        // Squad player
            $seasonAppearances >= self::LOW_APPEARANCES => 1.0,      // Rotation player
            default => 1.5,                                           // Barely plays
        };
    }

    /**
     * Ability factor: elite players tend to extend their careers.
     *
     * @return float Multiplier (0.8 to 1.3)
     */
    private function getAbilityFactor(GamePlayer $player): float
    {
        $ability = (int) round(
            ($player->current_technical_ability + $player->current_physical_ability) / 2
        );

        return match (true) {
            $ability >= 80 => 0.8,   // Elite players delay
            $ability >= 70 => 0.9,
            $ability >= 60 => 1.0,
            $ability >= 50 => 1.1,
            default => 1.3,          // Low ability accelerates
        };
    }
}
