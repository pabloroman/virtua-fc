<?php

namespace App\Jobs;

use App\Game\Services\BudgetProjectionService;
use App\Game\Services\ContractService;
use App\Game\Services\CountryConfig;
use App\Game\Services\CupDrawService;
use App\Game\Services\InjuryService;
use App\Game\Services\LeagueFixtureGenerator;
use App\Game\Services\PlayerDevelopmentService;
use App\Game\Services\StandingsCalculator;
use App\Game\Services\SwissDrawService;
use App\Support\Money;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
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
        LeagueFixtureGenerator $leagueFixtureGenerator,
        StandingsCalculator $standingsCalculator,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
        BudgetProjectionService $budgetProjectionService,
        CupDrawService $cupDrawService,
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
        $matchdays = LeagueFixtureGenerator::loadMatchdays($this->competitionId, $this->season);
        $this->generateLeagueFixtures($leagueFixtureGenerator, $matchdays);

        // Step 3: Initialize standings
        $this->initializeStandings($standingsCalculator);

        // Step 4: Initialize game players for all leagues
        $this->initializeGamePlayers($allTeams, $allPlayers, $contractService, $developmentService);

        // Step 5: Career-mode only extras
        if ($this->gameMode === Game::MODE_CAREER) {
            $game->refresh();
            $budgetProjectionService->generateProjections($game);
            $this->conductInitialCupDraws($cupDrawService);
            $this->initializeSwissFormatCompetitions($allTeams, $allPlayers, $contractService, $developmentService, $standingsCalculator);
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

    private function generateLeagueFixtures(LeagueFixtureGenerator $leagueFixtureGenerator, array $matchdays): void
    {
        // Idempotency: skip if fixtures already exist for main competition
        if (GameMatch::where('game_id', $this->gameId)->where('competition_id', $this->competitionId)->exists()) {
            return;
        }

        $teamIds = CompetitionEntry::where('game_id', $this->gameId)
            ->where('competition_id', $this->competitionId)
            ->pluck('team_id')
            ->toArray();

        $fixtures = $leagueFixtureGenerator->generate($teamIds, $matchdays);

        $rows = [];
        foreach ($fixtures as $fixture) {
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $this->gameId,
                'competition_id' => $this->competitionId,
                'round_number' => $fixture['matchday'],
                'home_team_id' => $fixture['homeTeamId'],
                'away_team_id' => $fixture['awayTeamId'],
                'scheduled_date' => Carbon::parse($fixture['date']),
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            GameMatch::insert($chunk);
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

    private function conductInitialCupDraws(CupDrawService $cupDrawService): void
    {
        $cupCompetitions = Competition::where('handler_type', 'knockout_cup')->get();

        foreach ($cupCompetitions as $competition) {
            if ($cupDrawService->needsDrawForRound($this->gameId, $competition->id, 1)) {
                $cupDrawService->conductDraw($this->gameId, $competition->id, 1);
            }
        }
    }

    private function initializeSwissFormatCompetitions(
        Collection $allTeams,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
        StandingsCalculator $standingsCalculator,
    ): void {
        $countryConfig = app(CountryConfig::class);
        $game = Game::find($this->gameId);
        $countryCode = $game?->country ?? 'ES';
        $continentalIds = $countryConfig->continentalSupportIds($countryCode);

        // Also include any swiss_format competitions not in the config (backward compat)
        $swissIds = Competition::where('handler_type', 'swiss_format')->pluck('id')->toArray();
        $allIds = array_unique(array_merge($continentalIds, $swissIds));

        foreach ($allIds as $competitionId) {
            $competition = Competition::find($competitionId);
            if (!$competition) {
                continue;
            }

            $participates = CompetitionEntry::where('game_id', $this->gameId)
                ->where('competition_id', $competition->id)
                ->where('team_id', $this->teamId)
                ->exists();

            if (!$participates) {
                continue;
            }

            $teamsFilePath = base_path("data/{$this->season}/{$competition->id}/teams.json");
            if (!file_exists($teamsFilePath)) {
                continue;
            }
            $teamsData = json_decode(file_get_contents($teamsFilePath), true);
            $clubs = $teamsData['clubs'] ?? [];

            $this->generateSwissFixtures($competition->id, $clubs, $allTeams);

            // Initialize standings for Swiss competition
            $teamIds = CompetitionEntry::where('game_id', $this->gameId)
                ->where('competition_id', $competition->id)
                ->pluck('team_id')
                ->toArray();
            $standingsCalculator->initializeStandings($this->gameId, $competition->id, $teamIds);

            $this->initializeSwissFormatPlayersFromData($competition->id, $clubs, $allTeams, $allPlayers, $contractService, $developmentService);
        }
    }

    private function generateSwissFixtures(string $competitionId, array $clubs, Collection $allTeams): void
    {
        // Idempotency: skip if fixtures already exist
        if (GameMatch::where('game_id', $this->gameId)->where('competition_id', $competitionId)->exists()) {
            return;
        }

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

        if (count($drawTeams) < 36) {
            return;
        }

        $schedulePath = base_path("data/{$this->season}/{$competitionId}/schedule.json");
        $scheduleData = json_decode(file_get_contents($schedulePath), true);

        $matchdayDates = [];
        foreach ($scheduleData['league'] as $md) {
            $matchdayDates[$md['round']] = $md['date'];
        }

        $drawService = new SwissDrawService();
        $fixtures = $drawService->generateFixtures($drawTeams, $matchdayDates);

        $rows = [];
        foreach ($fixtures as $fixture) {
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $this->gameId,
                'competition_id' => $competitionId,
                'round_number' => $fixture['matchday'],
                'home_team_id' => $fixture['homeTeamId'],
                'away_team_id' => $fixture['awayTeamId'],
                'scheduled_date' => Carbon::parse($fixture['date']),
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            GameMatch::insert($chunk);
        }
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
