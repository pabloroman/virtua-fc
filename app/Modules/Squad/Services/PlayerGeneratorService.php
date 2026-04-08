<?php

namespace App\Modules\Squad\Services;

use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\ClubProfile;
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


    /** @var array<string, string[]> Cached team player names by "gameId:teamId" */
    private array $teamNamesCache = [];

    /** @var array<string, string[]> Cached free agent names by gameId */
    private array $freeAgentNamesCache = [];

    public function __construct(
        private readonly ContractService $contractService,
        private readonly PlayerDevelopmentService $developmentService,
        private readonly PlayerValuationService $valuationService,
        private readonly PlayerAttributeSampler $sampler,
    ) {}

    /**
     * Seed name cache for a specific team from already-loaded data.
     *
     * Allows callers that already have the data (e.g. from a bulk query) to
     * populate caches without triggering any additional database queries.
     *
     * @param  string[]  $playerNames
     */
    public function seedCaches(string $gameId, string $teamId, array $playerNames): void
    {
        $key = "{$gameId}:{$teamId}";
        $this->teamNamesCache[$key] = $playerNames;
    }

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
            ? array_merge(
                $data->teamId ? $this->getOrLoadTeamPlayerNames($game->id, $data->teamId) : [],
                $this->getOrLoadFreeAgentNames($game->id)
            )
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

        $gamePlayer = GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $data->teamId,
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

        // Update cache with the newly created player
        $teamKey = "{$game->id}:{$data->teamId}";
        $this->teamNamesCache[$teamKey][] = $name;

        return $gamePlayer;
    }

    /**
     * Create multiple players in bulk using batch inserts.
     *
     * Computes all player data in memory, then does two chunked bulk inserts
     * (Player + GamePlayer) instead of individual creates per player.
     *
     * @param  Game  $game
     * @param  GeneratedPlayerData[]  $dataItems
     * @return array<array{playerId: string, playerName: string, position: string, teamId: string}>
     */
    public function createBulk(Game $game, array $dataItems): array
    {
        if (empty($dataItems)) {
            return [];
        }

        $minimumWage = $this->contractService->getMinimumWageForTeam($game->team);
        $seasonYear = (int) $game->season;
        $currentDate = $game->current_date ?? now();

        $playerRows = [];
        $gamePlayerRows = [];
        $results = [];
        $batchNames = [];

        foreach ($dataItems as $data) {
            $excludedNames = ($data->name === null)
                ? array_merge(
                    $this->getOrLoadTeamPlayerNames($game->id, $data->teamId),
                    $batchNames,
                )
                : [];
            $identity = $this->pickRandomIdentity(excludedNames: $excludedNames);
            $name = $data->name ?? $identity['name'];
            $nationality = $data->nationality ?? $identity['nationality'];
            $age = (int) $data->dateOfBirth->diffInYears($currentDate);

            $playerId = Str::uuid()->toString();
            $gamePlayerId = Str::uuid()->toString();

            $playerRows[] = [
                'id' => $playerId,
                'transfermarkt_id' => 'gen-' . Str::uuid()->toString(),
                'name' => $name,
                'nationality' => json_encode($nationality),
                'date_of_birth' => $data->dateOfBirth->format('Y-m-d'),
                'technical_ability' => $data->technical,
                'physical_ability' => $data->physical,
                'height' => $identity['height'] ?? null,
                'foot' => $identity['foot'] ?? null,
            ];

            $averageAbility = (int) round(($data->technical + $data->physical) / 2);
            $marketValue = $data->marketValueCents ?? $this->valuationService->abilityToMarketValue($averageAbility, $age);
            $marketValue = max(100_000_00, $marketValue);

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

            $annualWage = $this->contractService->calculateAnnualWage($marketValue, $minimumWage, $age);
            $contractUntil = Carbon::createFromDate($seasonYear + $data->contractYears, 6, 30);

            $gamePlayerRows[] = [
                'id' => $gamePlayerId,
                'game_id' => $game->id,
                'player_id' => $playerId,
                'team_id' => $data->teamId,
                'position' => $data->position,
                'market_value_cents' => $marketValue,
                'contract_until' => $contractUntil->format('Y-m-d'),
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
            ];

            // Update caches for subsequent iterations
            $teamKey = "{$game->id}:{$data->teamId}";
            $this->teamNamesCache[$teamKey][] = $name;
            $batchNames[] = $name;

            $results[] = [
                'playerId' => $gamePlayerId,
                'playerName' => $name,
                'position' => $data->position,
                'teamId' => $data->teamId,
            ];
        }

        // Bulk insert Player records
        foreach (array_chunk($playerRows, 500) as $chunk) {
            Player::insert($chunk);
        }

        // Bulk insert GamePlayer records
        foreach (array_chunk($gamePlayerRows, 500) as $chunk) {
            GamePlayer::insert($chunk);
        }

        return $results;
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
     * Build a GeneratedPlayerData for a replenishment player (mid-career, team-average ability).
     */
    public function buildReplenishmentPlayerData(
        Game $game,
        string $teamId,
        string $position,
        int $teamAvgAbility,
    ): GeneratedPlayerData {
        $variance = mt_rand(-10, 10);
        $baseAbility = max(35, min(90, $teamAvgAbility + $variance));

        $techBias = mt_rand(-5, 5);
        $technical = max(30, min(95, $baseAbility + $techBias));
        $physical = max(30, min(95, $baseAbility - $techBias));

        $ageRoll = mt_rand(1, 100);
        $age = match (true) {
            $ageRoll <= 10 => mt_rand(19, 20),
            $ageRoll <= 40 => mt_rand(21, 23),
            $ageRoll <= 75 => mt_rand(24, 27),
            default => mt_rand(28, 31),
        };

        $dateOfBirth = $game->current_date->copy()->subYears($age)->subDays(mt_rand(0, 364));

        return new GeneratedPlayerData(
            teamId: $teamId,
            position: $position,
            technical: $technical,
            physical: $physical,
            dateOfBirth: $dateOfBirth,
            contractYears: mt_rand(2, 4),
        );
    }

    /**
     * Base ability mean by reputation tier for AI academy graduates.
     *
     * Mirrors YouthAcademyService::ACADEMY_BASE_QUALITY but with a maturity
     * bonus to reflect 1-3 years of development before first-team promotion.
     */
    private const REPUTATION_BASE_QUALITY = [
        0 => 45,   // local
        1 => 50,   // modest
        2 => 57,   // established
        3 => 67,   // continental
        4 => 74,   // elite
    ];

    private const ABILITY_STD_DEV = 6;

    /**
     * Average potential upside (points above current ability) per reputation tier.
     */
    private const POTENTIAL_UPSIDE_MEAN = [
        0 => 12,
        1 => 12,
        2 => 10,
        3 => 8,
        4 => 8,
    ];

    private const POTENTIAL_UPSIDE_STD_DEV = 5;

    /**
     * Absolute minimum potential guaranteed by reputation tier.
     */
    private const POTENTIAL_FLOOR = [
        0 => 42,
        1 => 45,
        2 => 50,
        3 => 55,
        4 => 60,
    ];

    /**
     * Build a GeneratedPlayerData for an AI academy graduate (young, reputation-based ability).
     *
     * Simulates a player being promoted from an AI team's academy to the first team.
     * Quality is driven by the team's reputation, mirroring how the user's youth
     * academy uses tiers to determine prospect quality.
     */
    public function buildYouthPlayerData(
        Game $game,
        string $teamId,
        string $position,
        string $reputationLevel,
    ): GeneratedPlayerData {
        $reputationIndex = ClubProfile::getReputationTierIndex($reputationLevel);
        $abilityMean = self::REPUTATION_BASE_QUALITY[$reputationIndex];

        $technical = $this->sampler->sampleAbility($abilityMean, self::ABILITY_STD_DEV, 30, 80);
        $physical = $this->sampler->sampleAbility($abilityMean, self::ABILITY_STD_DEV, 30, 80);

        $currentBest = max($technical, $physical);
        $potentialData = $this->sampler->generatePotentialFromAbility(
            $currentBest,
            self::POTENTIAL_UPSIDE_MEAN[$reputationIndex],
            self::POTENTIAL_UPSIDE_STD_DEV,
            self::POTENTIAL_FLOOR[$reputationIndex],
        );
        $potential = $potentialData['potential'];
        $potentialLow = $potentialData['potentialLow'];
        $potentialHigh = $potentialData['potentialHigh'];

        $ageRoll = mt_rand(1, 100);
        $age = match (true) {
            $ageRoll <= 20 => 20,
            $ageRoll <= 55 => 21,
            $ageRoll <= 85 => 22,
            default => 23,
        };

        $dateOfBirth = $game->current_date->copy()->subYears($age)->subDays(mt_rand(0, 364));

        return new GeneratedPlayerData(
            teamId: $teamId,
            position: $position,
            technical: $technical,
            physical: $physical,
            dateOfBirth: $dateOfBirth,
            contractYears: mt_rand(3, 5),
            potential: $potential,
            potentialLow: $potentialLow,
            potentialHigh: $potentialHigh,
        );
    }

    /**
     * Calculate the average ability across a collection of players.
     *
     * Accepts any collection with `game_technical_ability` and `game_physical_ability` attributes.
     */
    public function calculateTeamAverageAbility($players): int
    {
        if ($players->isEmpty()) {
            return 55;
        }

        $total = $players->sum(fn ($p) => (int) round(($p->game_technical_ability + $p->game_physical_ability) / 2));

        return (int) round($total / $players->count());
    }

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
     * Get cached free agent names, loading from DB on first access per game.
     *
     * @return string[]
     */
    private function getOrLoadFreeAgentNames(string $gameId): array
    {
        if (!isset($this->freeAgentNamesCache[$gameId])) {
            $this->freeAgentNamesCache[$gameId] = GamePlayer::where('game_id', $gameId)
                ->whereNull('team_id')
                ->join('players', 'game_players.player_id', '=', 'players.id')
                ->pluck('players.name')
                ->toArray();
        }

        return $this->freeAgentNamesCache[$gameId];
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
