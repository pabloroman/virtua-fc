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
     * Delete templates for national teams (World Cup rosters).
     */
    public function clearTemplatesForNationalTeams(string $season): void
    {
        DB::table('game_player_templates')
            ->where('season', $season)
            ->whereIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            })
            ->delete();
    }

    /**
     * Generate pre-computed templates for World Cup national team rosters.
     *
     * @return int Number of template rows generated
     */
    public function generateForWorldCup(string $season = '2025'): int
    {
        $this->clearTemplatesForNationalTeams($season);

        $basePath = base_path('data/2025/WC2026/teams');

        // Load national teams with roster files
        $nationalTeams = DB::table('teams')
            ->where('type', 'national')
            ->whereNotNull('transfermarkt_id')
            ->get(['id', 'transfermarkt_id']);

        // Load roster data per team and collect needed transfermarkt IDs
        $teamRosters = [];
        $neededTmIds = [];

        foreach ($nationalTeams as $team) {
            $filePath = "{$basePath}/{$team->transfermarkt_id}.json";
            if (!file_exists($filePath)) {
                continue;
            }

            $data = json_decode(file_get_contents($filePath), true);
            if (!$data || empty($data['players'])) {
                continue;
            }

            $teamRosters[] = ['team_id' => $team->id, 'players' => $data['players']];
            foreach ($data['players'] as $playerData) {
                if (!empty($playerData['id'])) {
                    $neededTmIds[] = $playerData['id'];
                }
            }
        }

        // Only load players that appear in roster files
        $allPlayers = DB::table('players')
            ->select('id', 'transfermarkt_id', 'date_of_birth', 'technical_ability', 'physical_ability')
            ->whereIn('transfermarkt_id', array_unique($neededTmIds))
            ->get()
            ->keyBy('transfermarkt_id');

        $processedPlayerIds = [];
        $rows = [];

        foreach ($teamRosters as $roster) {
            foreach ($roster['players'] as $playerData) {
                $row = $this->prepareTemplateRow($season, $roster['team_id'], $playerData, 0, $allPlayers);
                if ($row && !isset($processedPlayerIds[$row['player_id']])) {
                    $row['number'] = null; // WC templates must not store squad numbers
                    $rows[] = $row;
                    $processedPlayerIds[$row['player_id']] = true;
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('game_player_templates')->insert($chunk);
        }

        return count($rows);
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

        $countryConfig = app(CountryConfig::class);
        $competitionIds = $countryConfig->playerInitializationOrder($countryCode);
        $continentalIds = $countryConfig->continentalSupportIds($countryCode);
        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);

        // First pass: scan all JSON files to collect needed transfermarkt IDs
        $neededTmIds = [];

        foreach ($competitionIds as $competitionId) {
            if (in_array($competitionId, $continentalIds)) {
                continue;
            }
            $neededTmIds = array_merge($neededTmIds, $this->collectPlayerIdsFromCompetition($season, $competitionId));
        }

        foreach ($swissIds as $competitionId) {
            $neededTmIds = array_merge($neededTmIds, $this->collectPlayerIdsFromSwissTeams($season, $competitionId));
        }

        // Load only the players we actually need
        $players = DB::table('players')
            ->select('id', 'transfermarkt_id', 'date_of_birth', 'technical_ability', 'physical_ability')
            ->whereIn('transfermarkt_id', array_unique($neededTmIds))
            ->get()
            ->keyBy('transfermarkt_id');

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

        // Second pass: generate template rows
        foreach ($competitionIds as $competitionId) {
            if (in_array($competitionId, $continentalIds)) {
                continue;
            }

            $rows = $this->generateForCompetition($competitionId, $season, $allTeamIds, $players, $processedTeamIds, $processedPlayerIds);
            $totalCount += $this->insertAndTrack($rows, $processedTeamIds, $processedPlayerIds);
        }

        foreach ($swissIds as $competitionId) {
            $rows = $this->generateForSwissGapTeams($competitionId, $season, $allTeamIds, $players, $processedTeamIds, $processedPlayerIds);
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

        $referenceDate = Carbon::parse("{$season}-08-15");
        $defaultContract = '2027-06-30';
        $contractUntil = $defaultContract;

        if (!empty($playerData['contract']) && $playerData['contract'] !== '-') {
            try {
                $parsed = Carbon::parse($playerData['contract']);
                $year = $parsed->month > 6 ? $parsed->year + 1 : $parsed->year;
                $candidate = Carbon::createFromDate($year, 6, 30);

                if ($candidate->greaterThan($referenceDate)) {
                    $contractUntil = $candidate->toDateString();
                }
            } catch (\Exception $e) {
                // Invalid date — keep default
            }
        }
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

        return [
            'season' => $season,
            'player_id' => $player->id,
            'team_id' => $teamId,
            'number' => isset($playerData['number']) ? (int) $playerData['number'] : null,
            'position' => $playerData['position'] ?? 'Unknown',
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

    /**
     * Collect player transfermarkt IDs from a competition's JSON files without loading DB data.
     */
    private function collectPlayerIdsFromCompetition(string $season, string $competitionId): array
    {
        $basePath = base_path("data/{$season}/{$competitionId}");
        $teamsFilePath = "{$basePath}/teams.json";

        if (file_exists($teamsFilePath)) {
            $clubs = $this->loadClubsFromTeamsJson($teamsFilePath);
        } else {
            $clubs = $this->loadClubsFromTeamPoolFiles($basePath);
        }

        return $this->extractPlayerIdsFromClubs($clubs);
    }

    /**
     * Collect player transfermarkt IDs from a Swiss format competition's teams.json.
     */
    private function collectPlayerIdsFromSwissTeams(string $season, string $competitionId): array
    {
        $teamsFilePath = base_path("data/{$season}/{$competitionId}/teams.json");
        if (!file_exists($teamsFilePath)) {
            return [];
        }

        $teamsData = json_decode(file_get_contents($teamsFilePath), true);
        return $this->extractPlayerIdsFromClubs($teamsData['clubs'] ?? []);
    }

    private function extractPlayerIdsFromClubs(array $clubs): array
    {
        $ids = [];
        foreach ($clubs as $club) {
            foreach ($club['players'] ?? [] as $playerData) {
                if (!empty($playerData['id'])) {
                    $ids[] = $playerData['id'];
                }
            }
        }
        return $ids;
    }

    private function extractTransfermarktIdFromImage(string $imageUrl): ?string
    {
        if (preg_match('/\/(\d+)\.png$/', $imageUrl, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
