<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class YouthAcademyService
{
    /**
     * Youth academy tier effects on prospect generation.
     * [min_prospects, max_prospects, min_potential, max_potential, min_ability, max_ability]
     */
    private const TIER_EFFECTS = [
        0 => [0, 0, 0, 0, 0, 0],           // No academy
        1 => [1, 1, 60, 70, 35, 50],       // Basic
        2 => [1, 2, 65, 75, 40, 55],       // Good
        3 => [2, 3, 70, 82, 45, 60],       // Excellent
        4 => [2, 4, 75, 90, 50, 70],       // World-class
    ];

    /**
     * Spanish first names for youth players.
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
     * Spanish surnames for youth players.
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

    /**
     * Positions with weights for random selection.
     */
    private const POSITION_WEIGHTS = [
        'Goalkeeper' => 5,
        'Centre-Back' => 15,
        'Left-Back' => 8,
        'Right-Back' => 8,
        'Defensive Midfield' => 10,
        'Central Midfield' => 15,
        'Attacking Midfield' => 10,
        'Left Winger' => 8,
        'Right Winger' => 8,
        'Centre-Forward' => 13,
    ];

    public function __construct(
        private readonly ContractService $contractService,
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    /**
     * Generate youth prospects for the user's team based on academy tier.
     *
     * @return Collection<GamePlayer> Generated prospects
     */
    public function generateProspects(Game $game): Collection
    {
        $investment = $game->currentInvestment;
        $tier = $investment?->youth_academy_tier ?? 0;

        if ($tier === 0) {
            return collect();
        }

        $effects = self::TIER_EFFECTS[$tier];
        [$minProspects, $maxProspects, $minPotential, $maxPotential, $minAbility, $maxAbility] = $effects;

        $prospectCount = rand($minProspects, $maxProspects);
        $prospects = collect();

        for ($i = 0; $i < $prospectCount; $i++) {
            $prospect = $this->createProspect(
                $game,
                $minPotential,
                $maxPotential,
                $minAbility,
                $maxAbility
            );
            $prospects->push($prospect);
        }

        return $prospects;
    }

    /**
     * Create a single youth prospect.
     */
    private function createProspect(
        Game $game,
        int $minPotential,
        int $maxPotential,
        int $minAbility,
        int $maxAbility,
    ): GamePlayer {
        // Generate player base data
        $name = $this->generateName();
        $dateOfBirth = $this->generateDateOfBirth($game);
        $position = $this->selectPosition();
        $nationality = $this->selectNationality();

        // Generate abilities based on tier
        $technical = rand($minAbility, $maxAbility);
        $physical = rand($minAbility, $maxAbility);

        // Create Player record
        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'nationality' => $nationality,
            'date_of_birth' => $dateOfBirth,
            'technical_ability' => $technical,
            'physical_ability' => $physical,
        ]);

        // Calculate age for contract and wage
        $age = $dateOfBirth->age;

        // Youth players get low wages
        $marketValue = $this->calculateYouthMarketValue($technical, $physical, $age);
        $annualWage = $this->contractService->calculateAnnualWage($marketValue, 0, $age);

        // Youth contracts are 3 years
        $contractUntil = Carbon::createFromDate((int) $game->season + 3, 6, 30);

        // Generate potential based on tier range
        $potential = rand($minPotential, $maxPotential);
        $potentialVariance = rand(3, 8);
        $potentialLow = max($potential - $potentialVariance, max($technical, $physical));
        $potentialHigh = min($potential + $potentialVariance, 99);

        // Create GamePlayer record
        $gamePlayer = GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $game->team_id,
            'position' => $position,
            'market_value' => $this->formatMarketValue($marketValue),
            'market_value_cents' => $marketValue,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
            'signed_from' => 'Youth Academy',
            'joined_on' => Carbon::createFromDate((int) $game->season, 7, 1),
            'fitness' => rand(85, 100),
            'morale' => rand(70, 90),
            'durability' => InjuryService::generateDurability(),
            // Development fields
            'game_technical_ability' => $technical,
            'game_physical_ability' => $physical,
            'potential' => $potential,
            'potential_low' => $potentialLow,
            'potential_high' => $potentialHigh,
            'season_appearances' => 0,
        ]);

        return $gamePlayer;
    }

    /**
     * Generate a Spanish-style name.
     */
    private function generateName(): string
    {
        $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $surname = self::SURNAMES[array_rand(self::SURNAMES)];

        // 30% chance of double surname
        if (rand(1, 100) <= 30) {
            $surname2 = self::SURNAMES[array_rand(self::SURNAMES)];

            return "$firstName $surname $surname2";
        }

        return "$firstName $surname";
    }

    /**
     * Generate a date of birth for a 16-18 year old.
     */
    private function generateDateOfBirth(Game $game): Carbon
    {
        $currentYear = (int) $game->season;
        $age = rand(16, 18);
        $birthYear = $currentYear - $age;

        return Carbon::createFromDate($birthYear, rand(1, 12), rand(1, 28));
    }

    /**
     * Select a position based on weights.
     */
    private function selectPosition(): string
    {
        $totalWeight = array_sum(self::POSITION_WEIGHTS);
        $random = rand(1, $totalWeight);

        foreach (self::POSITION_WEIGHTS as $position => $weight) {
            $random -= $weight;
            if ($random <= 0) {
                return $position;
            }
        }

        return 'Central Midfield';
    }

    /**
     * Select nationality (mostly Spanish for La Liga academies).
     */
    private function selectNationality(): array
    {
        // 80% Spanish, 20% other
        if (rand(1, 100) <= 80) {
            return ['ESP'];
        }

        $otherNationalities = [
            ['ARG'], ['BRA'], ['FRA'], ['POR'], ['MAR'],
            ['COL'], ['URU'], ['MEX'], ['VEN'], ['ECU'],
        ];

        return $otherNationalities[array_rand($otherNationalities)];
    }

    /**
     * Calculate market value for a youth player.
     */
    private function calculateYouthMarketValue(int $technical, int $physical, int $age): int
    {
        $averageAbility = ($technical + $physical) / 2;

        // Base value: €100K-€2M based on ability
        $baseValue = match (true) {
            $averageAbility >= 65 => rand(100_000_000, 200_000_000),  // €1M-€2M
            $averageAbility >= 55 => rand(50_000_000, 100_000_000),   // €500K-€1M
            $averageAbility >= 45 => rand(20_000_000, 50_000_000),    // €200K-€500K
            default => rand(10_000_000, 20_000_000),                   // €100K-€200K
        };

        // Young players get a slight premium
        $ageMultiplier = match ($age) {
            16 => 1.2,
            17 => 1.1,
            18 => 1.0,
            default => 1.0,
        };

        return (int) ($baseValue * $ageMultiplier);
    }

    /**
     * Format market value for display.
     */
    private function formatMarketValue(int $cents): string
    {
        $euros = $cents / 100;

        if ($euros >= 1_000_000) {
            return '€'.round($euros / 1_000_000, 2).'m';
        }

        return '€'.round($euros / 1_000).'K';
    }

    /**
     * Get tier description for display.
     */
    public static function getTierDescription(int $tier): string
    {
        return match ($tier) {
            0 => 'No Academy',
            1 => 'Basic Academy',
            2 => 'Good Academy',
            3 => 'Excellent Academy',
            4 => 'World-class Academy',
            default => 'Unknown',
        };
    }

    /**
     * Get expected prospects description for a tier.
     */
    public static function getProspectInfo(int $tier): array
    {
        $effects = self::TIER_EFFECTS[$tier] ?? self::TIER_EFFECTS[0];

        return [
            'min_prospects' => $effects[0],
            'max_prospects' => $effects[1],
            'potential_range' => $effects[2] > 0 ? "{$effects[2]}-{$effects[3]}" : 'N/A',
        ];
    }
}
