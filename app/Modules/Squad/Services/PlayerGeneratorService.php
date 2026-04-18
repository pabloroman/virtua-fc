<?php

namespace App\Modules\Squad\Services;

use App\Modules\Squad\Configs\TeamRegionalOrigins;
use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\ClubProfile;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Player\Services\InjuryService;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Player\Services\PlayerTierService;
use App\Support\PositionSlotMapper;
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
    /**
     * Names in use across the entire game, keyed by gameId. Populated lazily
     * on first call (all teams + free agents in one query) and extended as new
     * players are generated. Cross-team scope is what prevents the issue where
     * two teams' generated players end up with the same name (issue #819).
     *
     * @var array<string, string[]>
     */
    private array $gameNamesCache = [];

    public function __construct(
        private readonly ContractService $contractService,
        private readonly PlayerDevelopmentService $developmentService,
        private readonly PlayerValuationService $valuationService,
        private readonly PlayerAttributeSampler $sampler,
        private readonly PlayerNameGenerator $nameGenerator,
    ) {}

    /**
     * Seed game-wide name cache from already-loaded data.
     *
     * Allows callers that already have the data (e.g. from a bulk query) to
     * populate the cache without triggering an additional database query.
     *
     * @param  string[]  $playerNames
     */
    public function seedCaches(string $gameId, array $playerNames): void
    {
        $this->gameNamesCache[$gameId] = array_values(array_unique($playerNames));
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
        $excludedNames = $data->name === null
            ? $this->getOrLoadGameNames($game->id)
            : [];
        $region = $this->resolveTeamRegion($data->teamId);
        $identity = $this->pickRandomIdentity(
            excludedNames: $excludedNames,
            region: $region,
        );
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
            'secondary_positions' => $this->generatePositions($data->position),
            'market_value_cents' => $marketValue,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
            'durability' => InjuryService::generateDurability(),
            'game_technical_ability' => $data->technical,
            'game_physical_ability' => $data->physical,
            'potential' => $potential,
            'potential_low' => $potentialLow,
            'potential_high' => $potentialHigh,
            'tier' => PlayerTierService::tierFromMarketValue($marketValue),
        ]);

        // Generated players (youth grads, replenishment) always belong to
        // teams in the active scope, so they always need a match-state row.
        $matchState = GamePlayerMatchState::createWithDefaults(
            $gamePlayer->id,
            $game->id,
            mt_rand($data->fitnessMin, $data->fitnessMax),
            mt_rand($data->moraleMin, $data->moraleMax),
        );

        // Set relations to avoid lazy-load when caller accesses derived
        // attributes via the GamePlayer accessor delegates.
        $gamePlayer->setRelation('player', $player);
        $gamePlayer->setRelation('matchState', $matchState);

        // Update cache with the newly created player so subsequent generations in the
        // same request don't collide with it.
        $this->gameNamesCache[$game->id][] = $name;

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
        $matchStateRows = [];
        $results = [];
        $batchNames = [];

        // One query resolves every team's regional-naming flag for the batch,
        // so Basque / Catalan clubs get appropriate names without per-player
        // team lookups inside the hot loop.
        $teamRegions = $this->resolveTeamRegions(
            array_unique(array_filter(array_map(fn ($d) => $d->teamId, $dataItems)))
        );

        foreach ($dataItems as $data) {
            $excludedNames = $data->name === null
                ? array_merge($this->getOrLoadGameNames($game->id), $batchNames)
                : [];
            $region = $teamRegions[$data->teamId] ?? null;
            $identity = $this->pickRandomIdentity(
                excludedNames: $excludedNames,
                region: $region,
            );
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
                'durability' => InjuryService::generateDurability(),
                'game_technical_ability' => $data->technical,
                'game_physical_ability' => $data->physical,
                'potential' => $potential,
                'potential_low' => $potentialLow,
                'potential_high' => $potentialHigh,
                'tier' => PlayerTierService::tierFromMarketValue($marketValue),
            ];

            // Bulk-generated players (youth grads, replenishment) always end
            // up on active-scope teams, so they always get a satellite row.
            $matchStateRows[] = [
                'game_player_id' => $gamePlayerId,
                'game_id' => $game->id,
                'fitness' => mt_rand($data->fitnessMin, $data->fitnessMax),
                'morale' => mt_rand($data->moraleMin, $data->moraleMax),
            ];

            // Update caches for subsequent iterations in the same batch.
            $this->gameNamesCache[$game->id][] = $name;
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

        // Bulk insert satellite match-state rows
        GamePlayerMatchState::createForPlayers($matchStateRows);

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
     * Domestic-nationality share for weighted name selection: 75% domestic, 25% any.
     */
    private const DOMESTIC_NATIONALITY_WEIGHT = 75;

    /**
     * Maximum attempts to draw a non-colliding name before giving up and
     * accepting the last candidate. With Faker's per-locale name space
     * (tens of thousands of combinations) this almost never loops more
     * than once in practice.
     */
    private const NAME_RETRY_ATTEMPTS = 10;

    /**
     * Pick a random identity (name + nationality + height + foot) for a generated player.
     *
     * @param  string|null  $nationality    Exact nationality filter (100% match, e.g. for Athletic Bilbao)
     * @param  string|null  $teamCountry    Country code for weighted selection (75% domestic / 25% any)
     * @param  string[]     $excludedNames  Names to exclude (game-wide player + academy names)
     * @param  string|null  $region         Regional naming override for Basque/Catalan clubs
     *                                      ({@see TeamRegionalOrigins}). Forces nationality to
     *                                      Spain (if not explicitly set) and uses a custom
     *                                      Faker provider instead of es_ES.
     */
    public function pickRandomIdentity(
        ?string $nationality = null,
        ?string $teamCountry = null,
        array $excludedNames = [],
        ?string $region = null,
    ): array {
        // A Basque/Catalan club always produces Spanish-nationality players —
        // the region flag only overrides the *name* source, not the passport.
        if ($region !== null && $nationality === null) {
            $nationality = 'Spain';
        }

        $chosenNationality = $this->pickNationality($nationality, $teamCountry);
        $name = $this->generateUniqueName($chosenNationality, $excludedNames, $region);

        return [
            'name' => $name,
            'nationality' => [$chosenNationality],
            'height' => $this->generateHeight(),
            'foot' => $this->generateFoot(),
        ];
    }

    /**
     * Pick a nationality honouring the exact filter first, then the 75/25 domestic
     * weighting when a team country is known, and finally a random footballing
     * nationality as a fallback.
     */
    private function pickNationality(?string $nationality, ?string $teamCountry): string
    {
        if ($nationality !== null) {
            return $nationality;
        }

        if ($teamCountry !== null && isset(self::COUNTRY_TO_NATIONALITY[$teamCountry])) {
            if (rand(1, 100) <= self::DOMESTIC_NATIONALITY_WEIGHT) {
                return self::COUNTRY_TO_NATIONALITY[$teamCountry];
            }
        }

        $pool = PlayerNameGenerator::supportedNationalities();

        return $pool[array_rand($pool)];
    }

    /**
     * Generate a name for the given nationality, retrying a small number of times
     * if Faker happens to return one that collides with an existing name.
     *
     * @param  string[]    $excludedNames
     * @param  string|null $region  Regional override for Basque/Catalan clubs.
     */
    private function generateUniqueName(string $nationality, array $excludedNames, ?string $region = null): string
    {
        if (empty($excludedNames)) {
            return $this->nameGenerator->generate($nationality, $region);
        }

        $excludedSet = array_flip($excludedNames);
        $candidate = $this->nameGenerator->generate($nationality, $region);

        for ($attempt = 1; $attempt < self::NAME_RETRY_ATTEMPTS && isset($excludedSet[$candidate]); $attempt++) {
            $candidate = $this->nameGenerator->generate($nationality, $region);
        }

        return $candidate;
    }

    /**
     * Look up a single team's regional-naming flag from {@see TeamRegionalOrigins}.
     */
    private function resolveTeamRegion(?string $teamId): ?string
    {
        if ($teamId === null) {
            return null;
        }

        $name = Team::whereKey($teamId)->value('name');

        return TeamRegionalOrigins::regionFor($name);
    }

    /**
     * Bulk-resolve regional-naming flags for a set of teamIds in a single query.
     *
     * @param  string[]  $teamIds
     * @return array<string, string|null>  Map of teamId → region code (or null).
     */
    private function resolveTeamRegions(array $teamIds): array
    {
        if (empty($teamIds)) {
            return [];
        }

        $names = Team::whereIn('id', $teamIds)->pluck('name', 'id')->all();

        $regions = [];
        foreach ($names as $teamId => $teamName) {
            $regions[$teamId] = TeamRegionalOrigins::regionFor($teamName);
        }

        return $regions;
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
     * Get cached game-wide player names (every team + free agents), loading
     * from DB on first access. Cross-team scope prevents generated academy
     * prospects from colliding with players on other teams (issue #819).
     *
     * @return string[]
     */
    private function getOrLoadGameNames(string $gameId): array
    {
        if (! isset($this->gameNamesCache[$gameId])) {
            $this->gameNamesCache[$gameId] = GamePlayer::where('game_players.game_id', $gameId)
                ->join('players', 'game_players.player_id', '=', 'players.id')
                ->pluck('players.name')
                ->toArray();
        }

        return $this->gameNamesCache[$gameId];
    }

    /**
     * Generate positions array for a computer-generated player.
     *
     * Always includes the primary position. ~50% get no extras,
     * ~40% get one adjacent position, ~10% get two.
     *
     * @return string[]
     */
    private function generatePositions(string $position): array
    {
        $adjacent = PositionSlotMapper::getAdjacentPositions($position);
        if (empty($adjacent)) {
            return [$position];
        }

        $rand = random_int(0, 99);
        if ($rand < 50) {
            return [$position];
        }

        $pick1 = $adjacent[array_rand($adjacent)];
        if ($rand < 90) {
            return [$position, $pick1];
        }

        $remaining = array_values(array_diff($adjacent, [$pick1]));
        if (empty($remaining)) {
            return [$position, $pick1];
        }

        return [$position, $pick1, $remaining[array_rand($remaining)]];
    }

}
