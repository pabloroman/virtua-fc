<?php

namespace App\Modules\Season\Jobs;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonSetupRunner;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Squad\Services\InjuryService;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Modules\Season\Services\SeasonInitializationService;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Support\Money;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetupNewGame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private bool $usedTemplates = false;

    public function __construct(
        public string $gameId,
        public string $teamId,
        public string $competitionId,
        public string $season,
        public string $gameMode,
    ) {}

    public function handle(
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
        SeasonSetupRunner $setupRunner,
        SeasonInitializationService $seasonInitService,
        LeagueFixtureProcessor $fixtureProcessor,
        StandingsResetProcessor $standingsProcessor,
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

        // Step 2: Initialize game players (template-based or fallback)
        $this->initializeGamePlayersFromTemplates($allTeams, $allPlayers, $contractService, $developmentService);

        // Step 3: Run shared setup processors
        if ($this->gameMode === Game::MODE_CAREER) {
            // Career mode: run all 4 shared processors (fixtures, standings, budget, cups/Swiss)
            $swissPotData = $this->buildSwissPotData($allTeams);

            $data = new SeasonTransitionData(
                oldSeason: '0',
                newSeason: $this->season,
                competitionId: $this->competitionId,
                isInitialSeason: true,
                metadata: $swissPotData ? ['swissPotData' => $swissPotData] : [],
            );

            $setupRunner->run($game->refresh(), $data);

            // Initialize players for Swiss format competitions (non-template path only)
            if (!$this->usedTemplates) {
                $this->initializeSwissFormatPlayers($allTeams, $allPlayers, $contractService, $developmentService);
            }
        } else {
            // Non-career mode: only fixtures + standings (no budget/cups)
            $data = new SeasonTransitionData(
                oldSeason: '0',
                newSeason: $this->season,
                competitionId: $this->competitionId,
                isInitialSeason: true,
            );

            $fixtureProcessor->process($game, $data);
            $standingsProcessor->process($game, $data);
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

    /**
     * Build Swiss pot data from JSON for all Swiss competitions (initial season only).
     *
     * @return array<string, array<array{id: string, pot: int, country: string}>>
     */
    private function buildSwissPotData(Collection $allTeams): array
    {
        $countryConfig = app(CountryConfig::class);
        $game = Game::find($this->gameId);
        $countryCode = $game->country ?? 'ES';

        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);
        $potData = [];

        foreach ($swissIds as $competitionId) {
            $teamsFilePath = base_path("data/{$this->season}/{$competitionId}/teams.json");
            if (!file_exists($teamsFilePath)) {
                continue;
            }

            $teamsData = json_decode(file_get_contents($teamsFilePath), true);
            $clubs = $teamsData['clubs'] ?? [];

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

            if (!empty($drawTeams)) {
                $potData[$competitionId] = $drawTeams;
            }
        }

        return $potData;
    }

    /**
     * Initialize players for Swiss format competitions (fallback path only).
     * Skipped when templates are used since all players are already loaded.
     */
    private function initializeSwissFormatPlayers(
        Collection $allTeams,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
    ): void {
        $countryConfig = app(CountryConfig::class);
        $game = Game::find($this->gameId);
        $countryCode = $game->country ?? 'ES';

        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);

        foreach ($swissIds as $competitionId) {
            $teamsFilePath = base_path("data/{$this->season}/{$competitionId}/teams.json");
            if (!file_exists($teamsFilePath)) {
                continue;
            }

            $teamsData = json_decode(file_get_contents($teamsFilePath), true);
            $clubs = $teamsData['clubs'] ?? [];
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
                if (GamePlayer::where('game_id', $this->gameId)->where('team_id', $team->id)->exists()) {
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
    }

    /**
     * Initialize game players from pre-computed templates, falling back to
     * the old per-player computation if templates don't exist.
     */
    private function initializeGamePlayersFromTemplates(
        Collection $allTeams,
        Collection $allPlayers,
        ContractService $contractService,
        PlayerDevelopmentService $developmentService,
    ): void {
        // Idempotency: skip if players already exist
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            $this->usedTemplates = true; // assume templates if players exist
            return;
        }

        $hasTemplates = DB::table('game_player_templates')
            ->where('season', $this->season)
            ->exists();

        if (!$hasTemplates) {
            // Fallback to old behavior
            $this->initializeGamePlayers($allTeams, $allPlayers, $contractService, $developmentService);
            return;
        }

        $this->usedTemplates = true;

        $templates = DB::table('game_player_templates')
            ->where('season', $this->season)
            ->get();

        $rows = [];

        foreach ($templates as $t) {
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $this->gameId,
                'player_id' => $t->player_id,
                'team_id' => $t->team_id,
                'number' => $t->number,
                'position' => $t->position,
                'market_value' => $t->market_value,
                'market_value_cents' => $t->market_value_cents,
                'contract_until' => $t->contract_until,
                'annual_wage' => $t->annual_wage,
                'fitness' => $t->fitness,
                'morale' => $t->morale,
                'durability' => $t->durability,
                'game_technical_ability' => $t->game_technical_ability,
                'game_physical_ability' => $t->game_physical_ability,
                'potential' => $t->potential,
                'potential_low' => $t->potential_low,
                'potential_high' => $t->potential_high,
                'season_appearances' => 0,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            GamePlayer::insert($chunk);
        }
    }

    // =====================================================================
    // Fallback methods — used when game_player_templates table is empty
    // =====================================================================

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
        $countryConfig = app(CountryConfig::class);
        $game = Game::find($this->gameId);
        $countryCode = $game->country ?? 'ES';

        $competitionIds = $countryConfig->playerInitializationOrder($countryCode);
        $continentalIds = $countryConfig->continentalSupportIds($countryCode);

        foreach ($competitionIds as $competitionId) {
            if (in_array($competitionId, $continentalIds)) {
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
