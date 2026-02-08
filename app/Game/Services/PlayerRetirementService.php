<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Str;

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
        private readonly ContractService $contractService,
        private readonly PlayerDevelopmentService $developmentService,
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
        $position = $retiringPlayer->position;
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

        // Generate biographical data
        $name = $this->generateName();
        $seasonYear = (int) $newSeason;
        $age = mt_rand(22, 28);
        $dateOfBirth = Carbon::createFromDate($seasonYear - $age, mt_rand(1, 12), mt_rand(1, 28));
        $nationality = $this->selectNationality();

        // Create the reference Player record
        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'nationality' => $nationality,
            'date_of_birth' => $dateOfBirth,
            'technical_ability' => $technical,
            'physical_ability' => $physical,
        ]);

        // Estimate market value based on ability and age
        $marketValue = $this->estimateMarketValue($replacementAbility, $age);
        $marketValue = max(100_000_00, $marketValue);
        $annualWage = $this->contractService->calculateAnnualWage($marketValue, 0, $age);
        $contractUntil = Carbon::createFromDate($seasonYear + 3, 6, 30);

        // Generate potential
        $potentialData = $this->developmentService->generatePotential($age, $replacementAbility, $marketValue);

        $gamePlayer = GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $retiringPlayer->team_id,
            'position' => $position,
            'market_value_cents' => $marketValue,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
            'signed_from' => 'Transfer',
            'joined_on' => Carbon::createFromDate($seasonYear, 7, 1),
            'fitness' => mt_rand(80, 95),
            'morale' => mt_rand(65, 80),
            'durability' => InjuryService::generateDurability(),
            'game_technical_ability' => $technical,
            'game_physical_ability' => $physical,
            'potential' => $potentialData['potential'],
            'potential_low' => $potentialData['low'],
            'potential_high' => $potentialData['high'],
            'season_appearances' => 0,
        ]);

        return $gamePlayer;
    }

    /**
     * Estimate market value for a replacement player based on ability and age.
     * Simplified version that avoids needing a fully hydrated GamePlayer.
     */
    private function estimateMarketValue(int $ability, int $age): int
    {
        $baseValue = match (true) {
            $ability >= 80 => mt_rand(20_000_000_00, 35_000_000_00),
            $ability >= 76 => mt_rand(10_000_000_00, 20_000_000_00),
            $ability >= 72 => mt_rand(5_000_000_00, 10_000_000_00),
            $ability >= 68 => mt_rand(2_000_000_00, 5_000_000_00),
            $ability >= 64 => mt_rand(1_000_000_00, 2_000_000_00),
            $ability >= 60 => mt_rand(500_000_00, 1_000_000_00),
            default => mt_rand(100_000_00, 500_000_00),
        };

        // Age multiplier (22-28 range, so generally positive)
        $ageMultiplier = match (true) {
            $age <= 23 => 1.3,
            $age <= 26 => 1.1,
            $age <= 28 => 1.0,
            default => 0.85,
        };

        return (int) round($baseValue * $ageMultiplier);
    }

    /**
     * Spanish first names for generated replacement players.
     */
    private const FIRST_NAMES = [
        'Alejandro', 'Pablo', 'Carlos', 'Daniel', 'David', 'Diego',
        'Fernando', 'Gabriel', 'Hugo', 'Ivan', 'Javier', 'Jorge',
        'Jose', 'Juan', 'Lucas', 'Luis', 'Manuel', 'Marco',
        'Mario', 'Martin', 'Miguel', 'Nicolas', 'Oscar', 'Pedro',
        'Rafael', 'Roberto', 'Ruben', 'Sergio', 'Victor', 'Adrian',
        'Alvaro', 'Andres', 'Antonio', 'Bruno', 'Eduardo', 'Enrique',
        'Felipe', 'Gonzalo', 'Hector', 'Ignacio', 'Jaime', 'Jesus',
        'Joaquin', 'Marcos', 'Mateo', 'Nacho', 'Pau', 'Raul',
        'Samuel', 'Santiago', 'Tomas', 'Xavier', 'Yeray', 'Iker',
        'Unai', 'Aitor', 'Asier', 'Gorka', 'Mikel', 'Oier',
    ];

    /**
     * Spanish surnames for generated replacement players.
     */
    private const SURNAMES = [
        'Garcia', 'Rodriguez', 'Martinez', 'Lopez', 'Gonzalez', 'Hernandez',
        'Perez', 'Sanchez', 'Ramirez', 'Torres', 'Flores', 'Rivera',
        'Gomez', 'Diaz', 'Reyes', 'Moreno', 'Jimenez', 'Ruiz',
        'Alvarez', 'Romero', 'Alonso', 'Navarro', 'Dominguez', 'Gil',
        'Vazquez', 'Serrano', 'Blanco', 'Molina', 'Morales', 'Ortega',
        'Delgado', 'Castro', 'Ortiz', 'Rubio', 'Marin', 'Sanz',
        'Nunez', 'Iglesias', 'Medina', 'Garrido', 'Cortes', 'Castillo',
        'Santos', 'Lozano', 'Guerrero', 'Cano', 'Prieto', 'Mendez',
        'Cruz', 'Calvo', 'Gallego', 'Vidal', 'Leon', 'Herrera',
        'Marquez', 'Cabrera', 'Aguilar', 'Vega', 'Campos', 'Fuentes',
    ];

    private function generateName(): string
    {
        $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $surname = self::SURNAMES[array_rand(self::SURNAMES)];

        if (mt_rand(1, 100) <= 30) {
            $surname2 = self::SURNAMES[array_rand(self::SURNAMES)];
            return "$firstName $surname $surname2";
        }

        return "$firstName $surname";
    }

    private function selectNationality(): array
    {
        if (mt_rand(1, 100) <= 80) {
            return ['ESP'];
        }

        $otherNationalities = [
            ['ARG'], ['BRA'], ['FRA'], ['POR'], ['MAR'],
            ['COL'], ['URU'], ['MEX'], ['VEN'], ['ECU'],
        ];

        return $otherNationalities[array_rand($otherNationalities)];
    }
}
