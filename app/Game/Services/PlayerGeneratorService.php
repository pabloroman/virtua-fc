<?php

namespace App\Game\Services;

use App\Game\DTO\GeneratedPlayerData;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Creates computer-generated players (Player + GamePlayer records).
 *
 * Centralises the shared boilerplate for spawning new players:
 * name generation, nationality selection, market value estimation,
 * potential generation, and the actual DB record creation.
 *
 * Used by YouthAcademyService, PlayerRetirementService, and any
 * future service that needs to create synthetic players.
 */
class PlayerGeneratorService
{
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

    public function __construct(
        private readonly ContractService $contractService,
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    /**
     * Create a computer-generated player from the given configuration.
     *
     * Handles:
     * - Name/nationality generation (if not provided in $data)
     * - Player (reference) record creation
     * - Market value estimation (if not provided)
     * - Potential generation (if not provided)
     * - GamePlayer record creation with durability, fitness, morale
     */
    public function create(Game $game, GeneratedPlayerData $data): GamePlayer
    {
        $name = $data->name ?? $this->generateName();
        $nationality = $data->nationality ?? $this->selectNationality();
        $age = $data->dateOfBirth->age;

        // Create the reference Player record
        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'nationality' => $nationality,
            'date_of_birth' => $data->dateOfBirth,
            'technical_ability' => $data->technical,
            'physical_ability' => $data->physical,
        ]);

        // Determine market value
        $averageAbility = (int) round(($data->technical + $data->physical) / 2);
        $marketValue = $data->marketValueCents ?? $this->estimateMarketValue($averageAbility, $age);
        $marketValue = max(100_000_00, $marketValue);

        // Determine potential
        if ($data->potential !== null) {
            $potential = $data->potential;
            $potentialLow = $data->potentialLow ?? max($potential - 5, $averageAbility);
            $potentialHigh = $data->potentialHigh ?? min($potential + 5, 99);
        } else {
            $potentialData = $this->developmentService->generatePotential($age, $averageAbility, $marketValue);
            $potential = $potentialData['potential'];
            $potentialLow = $potentialData['low'];
            $potentialHigh = $potentialData['high'];
        }

        // Calculate wage and contract
        $annualWage = $this->contractService->calculateAnnualWage($marketValue, 0, $age);
        $seasonYear = (int) $game->season;
        $contractUntil = Carbon::createFromDate($seasonYear + $data->contractYears, 6, 30);

        return GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $data->teamId,
            'position' => $data->position,
            'market_value_cents' => $marketValue,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
            'signed_from' => $data->signedFrom,
            'joined_on' => Carbon::createFromDate($seasonYear, 7, 1),
            'fitness' => mt_rand($data->fitnessMin, $data->fitnessMax),
            'morale' => mt_rand($data->moraleMin, $data->moraleMax),
            'durability' => InjuryService::generateDurability(),
            'game_technical_ability' => $data->technical,
            'game_physical_ability' => $data->physical,
            'potential' => $potential,
            'potential_low' => $potentialLow,
            'potential_high' => $potentialHigh,
            'season_appearances' => 0,
        ]);
    }

    /**
     * Generate a Spanish-style name.
     */
    public function generateName(): string
    {
        $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $surname = self::SURNAMES[array_rand(self::SURNAMES)];

        // 30% chance of double surname
        if (mt_rand(1, 100) <= 30) {
            $surname2 = self::SURNAMES[array_rand(self::SURNAMES)];
            return "$firstName $surname $surname2";
        }

        return "$firstName $surname";
    }

    /**
     * Select nationality (mostly Spanish for La Liga context).
     */
    public function selectNationality(): array
    {
        // 80% Spanish, 20% other
        if (mt_rand(1, 100) <= 80) {
            return ['ESP'];
        }

        $otherNationalities = [
            ['ARG'], ['BRA'], ['FRA'], ['POR'], ['MAR'],
            ['COL'], ['URU'], ['MEX'], ['VEN'], ['ECU'],
        ];

        return $otherNationalities[array_rand($otherNationalities)];
    }

    /**
     * Estimate market value based on average ability and age.
     */
    public function estimateMarketValue(int $ability, int $age): int
    {
        $baseValue = match (true) {
            $ability >= 80 => mt_rand(20_000_000_00, 35_000_000_00),
            $ability >= 76 => mt_rand(10_000_000_00, 20_000_000_00),
            $ability >= 72 => mt_rand(5_000_000_00, 10_000_000_00),
            $ability >= 68 => mt_rand(2_000_000_00, 5_000_000_00),
            $ability >= 64 => mt_rand(1_000_000_00, 2_000_000_00),
            $ability >= 60 => mt_rand(500_000_00, 1_000_000_00),
            $ability >= 55 => mt_rand(200_000_00, 500_000_00),
            $ability >= 45 => mt_rand(100_000_00, 200_000_00),
            default => mt_rand(50_000_00, 100_000_00),
        };

        $ageMultiplier = match (true) {
            $age <= 17 => 1.2,
            $age <= 19 => 1.1,
            $age <= 23 => 1.3,
            $age <= 26 => 1.1,
            $age <= 28 => 1.0,
            $age <= 30 => 0.85,
            $age <= 33 => 0.65,
            default => 0.45,
        };

        return (int) round($baseValue * $ageMultiplier);
    }
}
