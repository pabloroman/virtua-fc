<?php

namespace App\Jobs;

use App\Game\Services\BudgetProjectionService;
use App\Game\Services\ContractService;
use App\Game\Services\CountryConfig;
use App\Game\Services\InjuryService;
use App\Game\Services\PlayerDevelopmentService;
use App\Game\Services\SeasonInitializationService;
use App\Game\Services\StandingsCalculator;
use App\Support\Money;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SetupNewGame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $gameId,
        public string $teamId,
        public string $competitionId,
        public string $season,
        public string $gameMode,
    ) {}

    public function handle(
        StandingsCalculator $standingsCalculator,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
        BudgetProjectionService $budgetProjectionService,
        SeasonInitializationService $seasonInitService,
    ): void {
        // Idempotency: skip if already set up
        $game = Game::find($this->gameId);
        if (!$game || $game->isSetupComplete()) {
            return;
        }

        // Pre-load all reference data (2 queries instead of ~4,600)
        $allTeams = Team::whereNotNull('transfermarkt_id')->get()->keyBy('transfermarkt_id');
        $allPlayers = Player::all()->keyBy('transfermarkt_id');

        // Step 1: Copy competition team rosters into per-game table
        $this->copyCompetitionTeamsToGame();

        // Step 2: Generate league fixtures
        if (!GameMatch::where('game_id', $this->gameId)->where('competition_id', $this->competitionId)->exists()) {
            $seasonInitService->generateLeagueFixtures($this->gameId, $this->competitionId, $this->season);
        }

        // Step 3: Initialize standings
        $this->initializeStandings($standingsCalculator);

        // Step 4: Initialize game players for all leagues
        $this->initializeGamePlayers($allTeams, $allPlayers, $contractService, $developmentService);

        // Step 5: Career-mode only extras
        if ($this->gameMode === Game::MODE_CAREER) {
            $game->refresh();
            $budgetProjectionService->generateProjections($game);
            $seasonInitService->conductCupDraws($this->gameId, $game->country ?? 'ES');
            $this->initializeSwissFormatCompetitions($allTeams, $allPlayers, $contractService, $developmentService, $seasonInitService);
        }

        // Mark setup as complete
        Game::where('id', $this->gameId)->update(['setup_completed_at' => now()]);
    }

    private function copyCompetitionTeamsToGame(): void
    {
        // Idempotency: skip if already done
        if (CompetitionEntry::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $rows = CompetitionTeam::where('season', $this->season)
            ->get()
            ->map(fn ($ct) => [
                'game_id' => $this->gameId,
                'competition_id' => $ct->competition_id,
                'team_id' => $ct->team_id,
                'entry_round' => $ct->entry_round ?? 1,
            ])
            ->toArray();

        foreach (array_chunk($rows, 100) as $chunk) {
            CompetitionEntry::insert($chunk);
        }
    }

    private function initializeStandings(StandingsCalculator $standingsCalculator): void
    {
        // Idempotency: skip if standings already exist for main competition
        if (GameStanding::where('game_id', $this->gameId)->where('competition_id', $this->competitionId)->exists()) {
            return;
        }

        $teamIds = CompetitionEntry::where('game_id', $this->gameId)
            ->where('competition_id', $this->competitionId)
            ->pluck('team_id')
            ->toArray();

        $standingsCalculator->initializeStandings($this->gameId, $this->competitionId, $teamIds);
    }

    /**
     * Initialize game players for all teams, following the config-driven
     * dependency order: playable tiers → transfer pool → continental.
     */
    private function initializeGamePlayers(
        Collection $allTeams,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
    ): void {
        // Idempotency: skip if players already exist
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $countryConfig = app(CountryConfig::class);
        $game = Game::find($this->gameId);
        $countryCode = $game?->country ?? 'ES';

        // Get competitions in dependency order from country config
        $competitionIds = $countryConfig->playerInitializationOrder($countryCode);

        // Continental competitions (e.g., UCL) are handled separately —
        // they reuse rosters from tiers + transfer pool
        $continentalIds = $countryConfig->continentalSupportIds($countryCode);

        foreach ($competitionIds as $competitionId) {
            if (in_array($competitionId, $continentalIds)) {
                // Continental: skip here, handled in initializeSwissFormatCompetitions
                continue;
            }

            $this->initializeGamePlayersForCompetition(
                $competitionId,
                $allTeams,
                $allPlayers,
                $contractService,
                $developmentService,
            );
        }
    }

    private function initializeGamePlayersForCompetition(
        string $competitionId,
        Collection $allTeams,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
    ): void {
        $basePath = base_path("data/{$this->season}/{$competitionId}");
        $teamsFilePath = "{$basePath}/teams.json";

        if (file_exists($teamsFilePath)) {
            $clubs = $this->loadClubsFromTeamsJson($teamsFilePath);
        } else {
            $clubs = $this->loadClubsFromTeamPoolFiles($basePath);
        }

        if (empty($clubs)) {
            return;
        }

        $minimumWage = $contractService->getMinimumWageForCompetition($competitionId);
        $playerRows = [];

        foreach ($clubs as $club) {
            $transfermarktId = $club['transfermarktId'] ?? $this->extractTransfermarktIdFromImage($club['image'] ?? '');
            if (!$transfermarktId) {
                continue;
            }

            $team = $allTeams->get($transfermarktId);
            if (!$team) {
                continue;
            }

            $playersData = $club['players'] ?? [];
            foreach ($playersData as $playerData) {
                $row = $this->prepareGamePlayerRow($team, $playerData, $minimumWage, $allPlayers, $contractService, $developmentService);
                if ($row) {
                    $playerRows[] = $row;
                }
            }
        }

        foreach (array_chunk($playerRows, 100) as $chunk) {
            GamePlayer::insert($chunk);
        }
    }

    private function initializeSwissFormatCompetitions(
        Collection $allTeams,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
        SeasonInitializationService $seasonInitService,
    ): void {
        $countryConfig = app(CountryConfig::class);
        $game = Game::find($this->gameId);
        $countryCode = $game?->country ?? 'ES';

        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);

        foreach ($swissIds as $competitionId) {
            $teamsFilePath = base_path("data/{$this->season}/{$competitionId}/teams.json");
            if (!file_exists($teamsFilePath)) {
                continue;
            }
            $teamsData = json_decode(file_get_contents($teamsFilePath), true);
            $clubs = $teamsData['clubs'] ?? [];

            // Build pot data from JSON for first season
            $drawTeams = $this->buildDrawTeamsFromJson($clubs, $allTeams);

            // Idempotency: skip if fixtures already exist
            // (participation check is handled inside the service)
            if (!GameMatch::where('game_id', $this->gameId)->where('competition_id', $competitionId)->exists()) {
                $seasonInitService->initializeSwissCompetition(
                    $this->gameId,
                    $this->teamId,
                    $competitionId,
                    $this->season,
                    $drawTeams,
                );
            }

            $this->initializeSwissFormatPlayersFromData($competitionId, $clubs, $allTeams, $allPlayers, $contractService, $developmentService);
        }
    }

    /**
     * Build draw teams array from JSON club data (first season only).
     *
     * @return array<array{id: string, pot: int, country: string}>
     */
    private function buildDrawTeamsFromJson(array $clubs, Collection $allTeams): array
    {
        $drawTeams = [];
        foreach ($clubs as $club) {
            $transfermarktId = $club['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            $team = $allTeams->get($transfermarktId);
            if (!$team) {
                continue;
            }

            $drawTeams[] = [
                'id' => $team->id,
                'pot' => $club['pot'] ?? 4,
                'country' => $club['country'] ?? 'XX',
            ];
        }

        return $drawTeams;
    }

    private function initializeSwissFormatPlayersFromData(
        string $competitionId,
        array $clubs,
        Collection $allTeams,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
    ): void {
        $minimumWage = $contractService->getMinimumWageForCompetition($competitionId);

        foreach ($clubs as $club) {
            $transfermarktId = $club['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            $team = $allTeams->get($transfermarktId);
            if (!$team) {
                continue;
            }

            // Skip teams that already have game players (e.g., Spanish teams from ESP1)
            $hasPlayers = GamePlayer::where('game_id', $this->gameId)
                ->where('team_id', $team->id)
                ->exists();

            if ($hasPlayers) {
                continue;
            }

            $playersData = $club['players'] ?? [];
            $playerRows = [];

            foreach ($playersData as $playerData) {
                $row = $this->prepareGamePlayerRow($team, $playerData, $minimumWage, $allPlayers, $contractService, $developmentService);
                if ($row) {
                    $playerRows[] = $row;
                }
            }

            foreach (array_chunk($playerRows, 100) as $chunk) {
                GamePlayer::insert($chunk);
            }
        }
    }

    private function prepareGamePlayerRow(
        Team $team,
        array $playerData,
        int $minimumWage,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
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
        $annualWage = $contractService->calculateAnnualWage($marketValueCents, $minimumWage, $player->age);

        $currentAbility = (int) round(
            ($player->technical_ability + $player->physical_ability) / 2
        );
        $potentialData = $developmentService->generatePotential(
            $player->age,
            $currentAbility
        );

        return [
            'id' => Str::uuid()->toString(),
            'game_id' => $this->gameId,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'number' => isset($playerData['number']) ? (int) $playerData['number'] : null,
            'position' => $playerData['position'] ?? 'Unknown',
            'market_value' => $playerData['marketValue'] ?? null,
            'market_value_cents' => $marketValueCents,
            'contract_until' => $contractUntil,
            'annual_wage' => $annualWage,
            'fitness' => rand(90, 100),
            'morale' => rand(65, 80),
            'durability' => InjuryService::generateDurability(),
            'game_technical_ability' => $player->technical_ability,
            'game_physical_ability' => $player->physical_ability,
            'potential' => $potentialData['potential'],
            'potential_low' => $potentialData['low'],
            'potential_high' => $potentialData['high'],
            'season_appearances' => 0,
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
