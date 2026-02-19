<?php

namespace App\Modules\Squad\Services;

use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Squad\Services\InjuryService;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Modules\Squad\Services\PlayerValuationService;

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
    /** @var array<array{name: string, nationality: array}> Cached identity pool */
    private ?array $identityPool = null;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly PlayerDevelopmentService $developmentService,
        private readonly PlayerValuationService $valuationService,
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
        $identity = $this->pickRandomIdentity();
        $name = $data->name ?? $identity['name'];
        $nationality = $data->nationality ?? $identity['nationality'];
        $age = $data->dateOfBirth->age;

        // Create the reference Player record
        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => 'gen-' . Str::uuid()->toString(),
            'name' => $name,
            'nationality' => $nationality,
            'date_of_birth' => $data->dateOfBirth,
            'technical_ability' => $data->technical,
            'physical_ability' => $data->physical,
        ]);

        // Determine market value
        $averageAbility = (int) round(($data->technical + $data->physical) / 2);
        $marketValue = $data->marketValueCents ?? $this->valuationService->abilityToMarketValue($averageAbility, $age);
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
        $annualWage = $this->contractService->calculateAnnualWage($marketValue, $this->contractService->getMinimumWageForTeam($game->team), $age);
        $seasonYear = (int) $game->season;
        $contractUntil = Carbon::createFromDate($seasonYear + $data->contractYears, 6, 30);

        $number = GamePlayer::nextAvailableNumber($game->id, $data->teamId);

        return GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $data->teamId,
            'number' => $number,
            'position' => $data->position,
            'market_value_cents' => $marketValue,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
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
     * Generate a name from the identity pool.
     */
    public function generateName(): string
    {
        return $this->pickRandomIdentity()['name'];
    }

    /**
     * Select a nationality from the identity pool.
     */
    public function selectNationality(): array
    {
        return $this->pickRandomIdentity()['nationality'];
    }

    /**
     * Pick a random identity (name + nationality) from the pool.
     */
    public function pickRandomIdentity(?string $nationality = null): array
    {
        $pool = $this->getIdentityPool();

        if ($nationality !== null) {
            $filtered = array_filter($pool, fn (array $entry) => in_array($nationality, $entry['nationality']));

            if (! empty($filtered)) {
                return $filtered[array_rand($filtered)];
            }
        }

        return $pool[array_rand($pool)];
    }

    /**
     * Load and cache the identity pool from the data file.
     */
    private function getIdentityPool(): array
    {
        if ($this->identityPool === null) {
            $path = base_path('data/academy/players.json');
            $this->identityPool = json_decode(file_get_contents($path), true);
        }

        return $this->identityPool;
    }

}
