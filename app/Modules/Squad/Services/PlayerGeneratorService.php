<?php

namespace App\Modules\Squad\Services;

use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Player\Services\InjuryService;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Player\Services\PlayerValuationService;

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
    /** @var array<array{name: string, nationality: string}> Cached identity pool */
    private ?array $identityPool = null;

    /** @var array<string, int[]> Cached taken squad numbers by "gameId:teamId" */
    private array $takenNumbersCache = [];

    /** @var array<string, string[]> Cached team player names by "gameId:teamId" */
    private array $teamNamesCache = [];

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
        $excludedNames = ($data->name === null)
            ? $this->getOrLoadTeamPlayerNames($game->id, $data->teamId)
            : [];
        $identity = $this->pickRandomIdentity(excludedNames: $excludedNames);
        $name = $data->name ?? $identity['name'];
        $nationality = $data->nationality ?? $identity['nationality'];
        $age = (int) $data->dateOfBirth->diffInYears($game->current_date ?? now());

        // Create the reference Player record
        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => 'gen-' . Str::uuid()->toString(),
            'name' => $name,
            'nationality' => $nationality,
            'date_of_birth' => $data->dateOfBirth->format('Y-m-d'),
            'technical_ability' => $data->technical,
            'physical_ability' => $data->physical,
            'height' => $identity['height'] ?? null,
            'foot' => $identity['foot'] ?? null,
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

        $number = $this->findNextAvailableNumber($game->id, $data->teamId);

        $gamePlayer = GamePlayer::create([
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
            'tier' => PlayerTierService::tierFromMarketValue($marketValue),
        ]);

        // Set relation to avoid lazy-load when caller accesses $gamePlayer->player
        $gamePlayer->setRelation('player', $player);

        // Update caches with the newly created player
        $teamKey = "{$game->id}:{$data->teamId}";
        $this->takenNumbersCache[$teamKey][] = $number;
        $this->teamNamesCache[$teamKey][] = $name;

        return $gamePlayer;
    }

    /**
     * Country code → nationality string mapping for weighted selection.
     */
    private const COUNTRY_TO_NATIONALITY = [
        'ES' => 'Spain',
        'EN' => 'England',
        'FR' => 'France',
        'DE' => 'Germany',
        'IT' => 'Italy',
    ];

    /**
     * Pick a random identity (name + nationality + height + foot) from the pool.
     *
     * @param  string|null  $nationality    Exact nationality filter (100% match, e.g. for Athletic Bilbao)
     * @param  string|null  $teamCountry    Country code for weighted selection (75% domestic / 25% any)
     * @param  string[]     $excludedNames  Names to exclude (e.g. existing squad/academy names)
     */
    public function pickRandomIdentity(?string $nationality = null, ?string $teamCountry = null, array $excludedNames = []): array
    {
        $pool = $this->getIdentityPool();

        // Remove names already used in the squad to prevent duplicates
        if (! empty($excludedNames)) {
            $excludedSet = array_flip($excludedNames);
            $filtered = array_values(array_filter($pool, fn (array $entry) => ! isset($excludedSet[$entry['name']])));

            if (! empty($filtered)) {
                $pool = $filtered;
            }
        }

        // Exact nationality filter takes priority (e.g. Athletic Bilbao → 100% Spanish)
        if ($nationality !== null) {
            $filtered = array_filter($pool, fn (array $entry) => $entry['nationality'] === $nationality);

            if (! empty($filtered)) {
                return $this->withGeneratedAttributes($filtered[array_rand($filtered)]);
            }
        }

        // Weighted selection: 75% domestic, 25% any
        if ($teamCountry !== null && isset(self::COUNTRY_TO_NATIONALITY[$teamCountry])) {
            $domesticNationality = self::COUNTRY_TO_NATIONALITY[$teamCountry];

            if (rand(1, 100) <= 75) {
                $filtered = array_filter($pool, fn (array $entry) => $entry['nationality'] === $domesticNationality);

                if (! empty($filtered)) {
                    return $this->withGeneratedAttributes($filtered[array_rand($filtered)]);
                }
            }
        }

        return $this->withGeneratedAttributes($pool[array_rand($pool)]);
    }

    /**
     * Add randomly generated height and foot, and wrap nationality as array for DB storage.
     */
    private function withGeneratedAttributes(array $entry): array
    {
        $entry['nationality'] = (array) $entry['nationality'];
        $entry['height'] = $entry['height'] ?? $this->generateHeight();
        $entry['foot'] = $entry['foot'] ?? $this->generateFoot();

        return $entry;
    }

    /**
     * Generate a realistic height string (e.g. "1,78m").
     * Distribution centered around 180cm (range 168–196).
     */
    private function generateHeight(): string
    {
        $cm = rand(168, 196);
        $meters = intdiv($cm, 100);
        $remainder = $cm % 100;

        return sprintf('%d,%02dm', $meters, $remainder);
    }

    /**
     * Generate a preferred foot (70% right, 30% left).
     */
    private function generateFoot(): string
    {
        return rand(1, 100) <= 70 ? 'right' : 'left';
    }

    /**
     * Get cached team player names, loading from DB on first access per team.
     *
     * @return string[]
     */
    private function getOrLoadTeamPlayerNames(string $gameId, string $teamId): array
    {
        $key = "{$gameId}:{$teamId}";

        if (!isset($this->teamNamesCache[$key])) {
            $this->teamNamesCache[$key] = GamePlayer::where('game_id', $gameId)
                ->where('team_id', $teamId)
                ->join('players', 'game_players.player_id', '=', 'players.id')
                ->pluck('players.name')
                ->toArray();
        }

        return $this->teamNamesCache[$key];
    }

    /**
     * Find next available squad number using cached taken numbers.
     */
    private function findNextAvailableNumber(string $gameId, string $teamId): int
    {
        $key = "{$gameId}:{$teamId}";

        if (!isset($this->takenNumbersCache[$key])) {
            $this->takenNumbersCache[$key] = GamePlayer::where('game_id', $gameId)
                ->where('team_id', $teamId)
                ->whereNotNull('number')
                ->pluck('number')
                ->all();
        }

        $taken = $this->takenNumbersCache[$key];

        for ($n = 2; $n <= 99; $n++) {
            if (!in_array($n, $taken)) {
                return $n;
            }
        }

        return 99;
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
