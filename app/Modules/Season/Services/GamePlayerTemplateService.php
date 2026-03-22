<?php

namespace App\Modules\Season\Services;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Player\Services\InjuryService;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Player\Services\PlayerTierService;

class GamePlayerTemplateService
{
    /** @var array<string, list<string>> Transfermarkt ID → secondary positions */
    private ?array $secondaryPositionsMap = null;

    public function __construct(
        private ContractService $contractService,
        private PlayerDevelopmentService $developmentService,
    ) {}

    /**
     * Delete all templates for a season (call once before generating for multiple countries).
     */
    public function clearTemplates(string $season): void
    {
        DB::table('game_player_templates')->where('season', $season)->delete();
    }

    /**
     * Delete templates for a specific country's teams only.
     */
    public function clearTemplatesForCountry(string $season, string $countryCode): void
    {
        DB::table('game_player_templates')
            ->where('season', $season)
            ->whereIn('team_id', function ($query) use ($countryCode) {
                $query->select('id')->from('teams')->where('country', $countryCode);
            })
            ->delete();
    }

    /**
     * Generate pre-computed game_player_templates for a season and country.
     * Additive — call clearTemplates() first if a fresh start is needed.
     *
     * @return int Number of template rows generated
     */
    public function generateTemplates(string $season, string $countryCode): int
    {
        $allTeamIds = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        $allPlayers = DB::table('players')
            ->select('id', 'transfermarkt_id', 'date_of_birth', 'technical_ability', 'physical_ability')
            ->get()
            ->keyBy('transfermarkt_id');

        $countryConfig = app(CountryConfig::class);
        $competitionIds = $countryConfig->playerInitializationOrder($countryCode);
        $continentalIds = $countryConfig->continentalSupportIds($countryCode);

        $totalCount = 0;

        // Track already-processed teams (including from prior country runs)
        $processedTeamIds = DB::table('game_player_templates')
            ->where('season', $season)
            ->distinct()
            ->pluck('team_id')
            ->flip()
            ->toArray();

        // Track already-processed players to avoid duplicates across teams
        $processedPlayerIds = DB::table('game_player_templates')
            ->where('season', $season)
            ->distinct()
            ->pluck('player_id')
            ->flip()
            ->toArray();

        // Process tier + transfer pool competitions
        foreach ($competitionIds as $competitionId) {
            if (in_array($competitionId, $continentalIds)) {
                continue;
            }

            $rows = $this->generateForCompetition($competitionId, $season, $allTeamIds, $allPlayers, $processedTeamIds, $processedPlayerIds);
            $totalCount += $this->insertAndTrack($rows, $processedTeamIds, $processedPlayerIds);
        }

        // Swiss format gap teams (UCL, UEL — teams not already covered)
        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);
        foreach ($swissIds as $competitionId) {
            $rows = $this->generateForSwissGapTeams($competitionId, $season, $allTeamIds, $allPlayers, $processedTeamIds, $processedPlayerIds);
            $totalCount += $this->insertAndTrack($rows, $processedTeamIds, $processedPlayerIds);
        }

        return $totalCount;
    }

    private function insertAndTrack(array $rows, array &$processedTeamIds, array &$processedPlayerIds): int
    {
        foreach ($rows as $row) {
            $processedTeamIds[$row['team_id']] = true;
            $processedPlayerIds[$row['player_id']] = true;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('game_player_templates')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Generate template rows for a non-continental competition.
     */
    private function generateForCompetition(
        string $competitionId,
        string $season,
        array $allTeamIds,
        Collection $allPlayers,
        array $processedTeamIds = [],
        array $processedPlayerIds = [],
    ): array {
        $basePath = base_path("data/{$season}/{$competitionId}");
        $teamsFilePath = "{$basePath}/teams.json";

        if (file_exists($teamsFilePath)) {
            $clubs = $this->loadClubsFromTeamsJson($teamsFilePath);
        } else {
            $clubs = $this->loadClubsFromTeamPoolFiles($basePath);
        }

        if (empty($clubs)) {
            return [];
        }

        $minimumWage = $this->contractService->getMinimumWageForCompetition($competitionId);
        $rows = [];

        foreach ($clubs as $club) {
            $transfermarktId = $club['transfermarktId'] ?? $this->extractTransfermarktIdFromImage($club['image'] ?? '');
            if (!$transfermarktId) {
                continue;
            }

            $teamId = $allTeamIds[$transfermarktId] ?? null;
            if (!$teamId) {
                continue;
            }

            // Skip teams already processed by a prior country run
            if (isset($processedTeamIds[$teamId])) {
                continue;
            }

            foreach ($club['players'] ?? [] as $playerData) {
                $row = $this->prepareTemplateRow($season, $teamId, $playerData, $minimumWage, $allPlayers);
                if ($row && !isset($processedPlayerIds[$row['player_id']])) {
                    $rows[] = $row;
                    $processedPlayerIds[$row['player_id']] = true;
                }
            }
        }

        return $rows;
    }

    /**
     * Generate template rows for Swiss format gap teams (teams not already processed).
     */
    private function generateForSwissGapTeams(
        string $competitionId,
        string $season,
        array $allTeamIds,
        Collection $allPlayers,
        array $processedTeamIds,
        array $processedPlayerIds = [],
    ): array {
        $teamsFilePath = base_path("data/{$season}/{$competitionId}/teams.json");
        if (!file_exists($teamsFilePath)) {
            return [];
        }

        $teamsData = json_decode(file_get_contents($teamsFilePath), true);
        $clubs = $teamsData['clubs'] ?? [];
        $minimumWage = $this->contractService->getMinimumWageForCompetition($competitionId);
        $rows = [];

        foreach ($clubs as $club) {
            $transfermarktId = $club['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            $teamId = $allTeamIds[$transfermarktId] ?? null;
            if (!$teamId) {
                continue;
            }

            // Skip teams already processed from tier/pool competitions
            if (isset($processedTeamIds[$teamId])) {
                continue;
            }

            foreach ($club['players'] ?? [] as $playerData) {
                $row = $this->prepareTemplateRow($season, $teamId, $playerData, $minimumWage, $allPlayers);
                if ($row && !isset($processedPlayerIds[$row['player_id']])) {
                    $rows[] = $row;
                    $processedPlayerIds[$row['player_id']] = true;
                }
            }
        }

        return $rows;
    }

    /**
     * Prepare a single template row from player JSON data.
     * Mirrors SetupNewGame::prepareGamePlayerRow() but stores season instead of game_id.
     */
    private function prepareTemplateRow(
        string $season,
        string $teamId,
        array $playerData,
        int $minimumWage,
        Collection $allPlayers,
    ): ?array {
        $player = $allPlayers->get($playerData['id']);
        if (!$player) {
            return null;
        }

        $contractUntil = null;
        if (!empty($playerData['contract'])) {
            try {
                $contractUntil = Carbon::parse($playerData['contract'])->toDateString();
            } catch (\Exception $e) {
                // Ignore invalid dates
            }
        }

        $referenceDate = Carbon::parse("{$season}-08-15");
        $dob = Carbon::parse($player->date_of_birth);
        $age = (int) $dob->diffInYears($referenceDate);
        $marketValueCents = Money::parseMarketValue($playerData['marketValue'] ?? null);
        $annualWage = $this->contractService->calculateAnnualWage($marketValueCents, $minimumWage, $age);

        $currentAbility = (int) round(
            ($player->technical_ability + $player->physical_ability) / 2
        );
        $potentialData = $this->developmentService->generatePotential(
            $age,
            $currentAbility
        );

        $secondaryPositions = $this->getSecondaryPositions($playerData['id']);

        return [
            'season' => $season,
            'player_id' => $player->id,
            'team_id' => $teamId,
            'number' => isset($playerData['number']) ? (int) $playerData['number'] : null,
            'position' => $playerData['position'] ?? 'Unknown',
            'secondary_positions' => json_encode($secondaryPositions),
            'market_value' => $playerData['marketValue'] ?? null,
            'market_value_cents' => $marketValueCents,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
            'fitness' => 80,
            'morale' => 80,
            'durability' => InjuryService::generateDurability(),
            'game_technical_ability' => $player->technical_ability,
            'game_physical_ability' => $player->physical_ability,
            'potential' => $potentialData['potential'],
            'potential_low' => $potentialData['low'],
            'potential_high' => $potentialData['high'],
            'tier' => PlayerTierService::tierFromMarketValue($marketValueCents),
        ];
    }

    private function loadClubsFromTeamsJson(string $teamsFilePath): array
    {
        $data = json_decode(file_get_contents($teamsFilePath), true);
        return $data['clubs'] ?? [];
    }

    private function loadClubsFromTeamPoolFiles(string $basePath): array
    {
        $clubs = [];

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = json_decode(file_get_contents($filePath), true);
            if (!$data) {
                continue;
            }

            $clubs[] = [
                'image' => $data['image'] ?? '',
                'transfermarktId' => $this->extractTransfermarktIdFromImage($data['image'] ?? ''),
                'players' => $data['players'] ?? [],
            ];
        }

        return $clubs;
    }

    private function extractTransfermarktIdFromImage(string $imageUrl): ?string
    {
        if (preg_match('/\/(\d+)\.png$/', $imageUrl, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get secondary positions for a player by Transfermarkt ID.
     *
     * @return list<string>
     */
    private function getSecondaryPositions(string $transfermarktId): array
    {
        if ($this->secondaryPositionsMap === null) {
            $this->secondaryPositionsMap = $this->loadSecondaryPositionsMap();
        }

        return $this->secondaryPositionsMap[$transfermarktId] ?? [];
    }

    /**
     * Load the secondary positions data file, keyed by Transfermarkt ID.
     *
     * @return array<string, list<string>>
     */
    private function loadSecondaryPositionsMap(): array
    {
        $path = base_path('data/players/player_positions_ES.json');
        if (!file_exists($path)) {
            return [];
        }

        $entries = json_decode(file_get_contents($path), true);
        $map = [];

        foreach ($entries as $entry) {
            $map[$entry['id']] = $entry['positions'] ?? [];
        }

        return $map;
    }
}
