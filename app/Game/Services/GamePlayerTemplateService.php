<?php

namespace App\Game\Services;

use App\Models\Player;
use App\Models\Team;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GamePlayerTemplateService
{
    public function __construct(
        private ContractService $contractService,
        private PlayerDevelopmentService $developmentService,
    ) {}

    /**
     * Generate pre-computed game_player_templates for a season and country.
     * Truncates existing templates for the season first.
     *
     * @return int Number of template rows generated
     */
    public function generateTemplates(string $season, string $countryCode): int
    {
        DB::table('game_player_templates')->where('season', $season)->delete();

        $allTeams = Team::whereNotNull('transfermarkt_id')->get()->keyBy('transfermarkt_id');
        $allPlayers = Player::all()->keyBy('transfermarkt_id');

        $countryConfig = app(CountryConfig::class);
        $competitionIds = $countryConfig->playerInitializationOrder($countryCode);
        $continentalIds = $countryConfig->continentalSupportIds($countryCode);

        $templateRows = [];
        $processedTeamIds = [];

        // Process tier + transfer pool competitions
        foreach ($competitionIds as $competitionId) {
            if (in_array($competitionId, $continentalIds)) {
                continue;
            }

            $rows = $this->generateForCompetition($competitionId, $season, $allTeams, $allPlayers);
            foreach ($rows as $row) {
                $templateRows[] = $row;
                $processedTeamIds[$row['team_id']] = true;
            }
        }

        // Swiss format gap teams (UCL, UEL — teams not already covered)
        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);
        foreach ($swissIds as $competitionId) {
            $rows = $this->generateForSwissGapTeams($competitionId, $season, $allTeams, $allPlayers, $processedTeamIds);
            foreach ($rows as $row) {
                $templateRows[] = $row;
                $processedTeamIds[$row['team_id']] = true;
            }
        }

        foreach (array_chunk($templateRows, 500) as $chunk) {
            DB::table('game_player_templates')->insert($chunk);
        }

        return count($templateRows);
    }

    /**
     * Generate template rows for a non-continental competition.
     */
    private function generateForCompetition(
        string $competitionId,
        string $season,
        Collection $allTeams,
        Collection $allPlayers,
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

            $team = $allTeams->get($transfermarktId);
            if (!$team) {
                continue;
            }

            foreach ($club['players'] ?? [] as $playerData) {
                $row = $this->prepareTemplateRow($season, $team, $playerData, $minimumWage, $allPlayers);
                if ($row) {
                    $rows[] = $row;
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
        Collection $allTeams,
        Collection $allPlayers,
        array $processedTeamIds,
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

            $team = $allTeams->get($transfermarktId);
            if (!$team) {
                continue;
            }

            // Skip teams already processed from tier/pool competitions
            if (isset($processedTeamIds[$team->id])) {
                continue;
            }

            foreach ($club['players'] ?? [] as $playerData) {
                $row = $this->prepareTemplateRow($season, $team, $playerData, $minimumWage, $allPlayers);
                if ($row) {
                    $rows[] = $row;
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
        Team $team,
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

        $marketValueCents = Money::parseMarketValue($playerData['marketValue'] ?? null);
        $annualWage = $this->contractService->calculateAnnualWage($marketValueCents, $minimumWage, $player->age);

        $currentAbility = (int) round(
            ($player->technical_ability + $player->physical_ability) / 2
        );
        $potentialData = $this->developmentService->generatePotential(
            $player->age,
            $currentAbility
        );

        return [
            'season' => $season,
            'player_id' => $player->id,
            'team_id' => $team->id,
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
}
