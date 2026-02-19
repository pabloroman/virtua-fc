<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Services\GamePlayerTemplateService;
use App\Modules\Squad\Services\PlayerValuationService;
use App\Models\User;
use App\Support\Money;
use App\Support\TeamColors;
use Carbon\Carbon;
use Database\Seeders\ClubProfilesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SeedReferenceData extends Command
{
    protected $signature = 'app:seed-reference-data
                            {--fresh : Clear existing data before seeding}
                            {--profile=production : Profile to seed (production, test)}
                            {--country= : Seed only a specific country (e.g., ES)}';

    protected $description = 'Seed teams, competitions, fixtures, and players from 2025 season JSON data files';

    public function handle(): int
    {
        $profile = $this->option('profile');
        $countryFilter = $this->option('country');

        $validProfiles = ['production', 'test'];
        if (!in_array($profile, $validProfiles)) {
            $this->error("Unknown profile: {$profile}. Available: " . implode(', ', $validProfiles));
            return CommandAlias::FAILURE;
        }

        $this->info("Using profile: {$profile}");

        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        if (App::environment('local')) {
            $this->createDefaultUser();
        }

        $countryConfig = app(CountryConfig::class);

        // Determine which countries to seed based on profile
        $countryCodes = match ($profile) {
            'test' => ['XX'],
            default => $countryConfig->playableCountryCodes(),
        };

        if ($countryFilter) {
            $countryFilter = strtoupper($countryFilter);
            if (!in_array($countryFilter, $countryCodes)) {
                $this->error("Country '{$countryFilter}' not found in profile '{$profile}'.");
                return CommandAlias::FAILURE;
            }
            $countryCodes = [$countryFilter];
        }

        foreach ($countryCodes as $countryCode) {
            $this->seedCountry($countryCode, $countryConfig);
        }

        // Seed club profiles for all teams
        $this->info('Seeding club profiles...');
        $seeder = new ClubProfilesSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Generate pre-computed game player templates
        $this->generateGamePlayerTemplates($countryCodes);

        $this->displaySummary();

        return CommandAlias::SUCCESS;
    }

    /**
     * Seed all competitions for a country in dependency order:
     * tiers → domestic cups (supercup first) → transfer pool → continental.
     */
    private function seedCountry(string $countryCode, CountryConfig $countryConfig): void
    {
        $config = $countryConfig->get($countryCode);
        if (!$config) {
            $this->warn("No config found for country: {$countryCode}");
            return;
        }

        $this->newLine();
        $this->info("=== {$config['name']} ({$countryCode}) ===");

        // Step 1: Seed tier competitions (leagues with teams + players)
        foreach ($config['tiers'] ?? [] as $tier => $tierConfig) {
            $this->seedCompetition([
                'code' => $tierConfig['competition'],
                'path' => "data/2025/{$tierConfig['competition']}",
                'tier' => $tier,
                'handler' => $tierConfig['handler'] ?? 'league',
                'country' => $countryCode,
                'role' => 'primary',
            ]);
        }

        // Step 2: Seed domestic cups — supercup first so main cup can look up
        // supercup teams for entry_round calculation
        $cupIds = array_keys($config['domestic_cups'] ?? []);
        $supercupConfig = $countryConfig->supercup($countryCode);
        if ($supercupConfig) {
            $supercupId = $supercupConfig['competition'];
            $cupIds = array_values(array_diff($cupIds, [$supercupId]));
            array_unshift($cupIds, $supercupId);
        }

        foreach ($cupIds as $cupId) {
            $cupConfig = $config['domestic_cups'][$cupId];
            $this->seedCompetition([
                'code' => $cupId,
                'path' => "data/2025/{$cupId}",
                'tier' => 0,
                'handler' => $cupConfig['handler'] ?? 'knockout_cup',
                'country' => $countryCode,
                'role' => 'domestic_cup',
            ]);
        }

        // Step 3: Seed transfer pool (foreign leagues + EUR pool)
        $support = $countryConfig->support($countryCode);
        foreach ($support['transfer_pool'] ?? [] as $code => $poolConfig) {
            $this->seedCompetition([
                'code' => $code,
                'path' => "data/2025/{$code}",
                'tier' => 1,
                'handler' => $poolConfig['handler'] ?? 'league',
                'country' => $poolConfig['country'] ?? 'EU',
                'role' => $poolConfig['role'] ?? 'foreign',
            ]);
        }

        // Step 4: Seed continental competitions (link existing teams)
        foreach ($support['continental'] ?? [] as $code => $continentalConfig) {
            $this->seedCompetition([
                'code' => $code,
                'path' => "data/2025/{$code}",
                'tier' => 0,
                'handler' => $continentalConfig['handler'] ?? 'swiss_format',
                'country' => $continentalConfig['country'] ?? 'EU',
                'role' => 'european',
            ]);
        }
    }

    private function createDefaultUser(): void
    {
        User::firstOrCreate(
            ['email' => 'test@test.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ]
        );

        $this->line("Default user: test@test.com / password");
    }

    private function clearExistingData(): void
    {
        $this->info('Clearing existing reference data...');

        // Clear game-scoped tables first
        DB::table('game_players')->delete();
        DB::table('match_events')->delete();
        DB::table('cup_ties')->delete();
        DB::table('game_standings')->delete();
        DB::table('game_matches')->delete();
        DB::table('games')->delete();

        // Clear reference tables
        DB::table('game_player_templates')->delete();
        DB::table('competition_teams')->delete();
        DB::table('players')->delete();
        DB::table('teams')->delete();
        DB::table('competitions')->delete();

        $this->info('Cleared.');
    }

    private function seedCompetition(array $config): void
    {
        $basePath = base_path($config['path']);
        $code = $config['code'];
        $tier = $config['tier'];
        $handler = $config['handler'] ?? 'league';
        $country = $config['country'] ?? 'ES';
        $role = $config['role'] ?? 'foreign';
        $configName = $config['name'] ?? null;

        $isCup = in_array($handler, ['knockout_cup', 'group_stage_cup']);
        $isSwiss = $handler === 'swiss_format';
        $isTeamPool = $handler === 'team_pool';

        $this->info("Seeding {$code}...");

        if ($isTeamPool) {
            $this->seedTeamPoolCompetition($basePath, $code, $tier, $handler, $country, $role, $configName);
        } elseif ($isSwiss) {
            $this->seedSwissFormatCompetition($basePath, $code, $tier, $handler, $country, $role);
        } elseif ($isCup) {
            $this->seedCupCompetition($basePath, $code, $tier, $handler, $country, $role);
        } else {
            $this->seedLeagueCompetition($basePath, $code, $tier, $handler, $country, $role, $configName);
        }
    }

    private function seedLeagueCompetition(string $basePath, string $code, int $tier, string $handler, string $country, string $role = 'foreign', ?string $configName = null): void
    {
        $teamsData = $this->loadJson("{$basePath}/teams.json");

        // Handle foreign leagues with simpler JSON structure
        $seasonId = $teamsData['seasonID'] ?? '2025';
        $leagueName = $teamsData['name'] ?? $configName ?? $code;

        // Normalize teams data for seedCompetitionRecord
        $normalizedData = [
            'name' => $leagueName,
            'seasonID' => $seasonId,
        ];

        // Seed competition record
        $this->seedCompetitionRecord($code, $normalizedData, $tier, 'league', $handler, $country, $role);

        // Build team ID mapping (transfermarktId -> UUID)
        $teamIdMap = $this->seedTeams($teamsData['clubs'], $code, $seasonId, $country);

        // Seed players (embedded in teams data)
        $this->seedPlayersFromTeams($teamsData['clubs'], $teamIdMap);

    }

    private function seedCupCompetition(string $basePath, string $code, int $tier, string $handler, string $country, string $role = 'domestic_cup'): void
    {
        $teamsData = $this->loadJson("{$basePath}/teams.json");

        $season = '2025';

        // Seed competition record
        $this->seedCompetitionRecord($code, $teamsData, $tier, 'cup', $handler, $country, $role);

        // Seed cup teams (link existing teams to cup)
        $this->seedCupTeams($teamsData['clubs'], $code, $season, $country);
    }

    private function seedSwissFormatCompetition(string $basePath, string $code, int $tier, string $handler, string $country, string $role = 'european'): void
    {
        $teamsData = $this->loadJson("{$basePath}/teams.json");

        $season = $teamsData['seasonID'] ?? '2025';

        // Swiss format uses 'league' type so standings are updated during league phase
        $this->seedCompetitionRecord($code, $teamsData, $tier, 'league', $handler, $country, $role);

        // Seed teams (links existing teams by transfermarkt_id, like cups)
        $teamIdMap = $this->seedSwissFormatTeams($teamsData['clubs'], $code, $season);

        // Seed embedded player data if present (clubs that have a 'players' array)
        $this->seedPlayersFromTeams($teamsData['clubs'], $teamIdMap);

        // Swiss league phase fixtures are generated per-game by SetupNewGame

        $this->line("  Swiss format competition seeded successfully");
    }

    /**
     * Seed a player pool competition from individual team JSON files.
     * Each file is named {transfermarkt_id}.json and contains {id, players}.
     * Teams must already exist from their league seeding.
     */
    private function seedTeamPoolCompetition(string $basePath, string $code, int $tier, string $handler, string $country, string $role, ?string $configName = null): void
    {
        $season = '2025';

        $this->seedCompetitionRecord($code, ['name' => $configName ?? $code, 'seasonID' => $season], $tier, 'league', $handler, $country, $role);

        // Get existing teams by transfermarkt_id
        $teamsByTransfermarktId = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        $teamIdMap = [];
        $clubs = [];

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = $this->loadJson($filePath);
            $transfermarktId = $this->extractTransfermarktIdFromImage($data['image'] ?? '');

            if (!$transfermarktId) {
                continue;
            }

            // Find or create team
            $teamId = $teamsByTransfermarktId[$transfermarktId] ?? null;

            // Use per-team country from JSON if available, fall back to pool country
            $teamCountry = $data['country'] ?? $country;

            if (!$teamId) {
                $teamId = Str::uuid()->toString();
                DB::table('teams')->insert([
                    'id' => $teamId,
                    'transfermarkt_id' => $transfermarktId,
                    'name' => $data['name'] ?? "Unknown ({$transfermarktId})",
                    'country' => $teamCountry,
                    'image' => $data['image'] ?? null,
                    'stadium_name' => $data['stadiumName'] ?? null,
                    'stadium_seats' => isset($data['stadiumSeats'])
                        ? (int) str_replace(['.', ','], '', $data['stadiumSeats'])
                        : 0,
                ]);
                $teamsByTransfermarktId[$transfermarktId] = $teamId;
            }

            $teamIdMap[$transfermarktId] = $teamId;

            // Link team to competition
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => $code,
                    'team_id' => $teamId,
                    'season' => $season,
                ],
                []
            );

            // Normalize to clubs format for seedPlayersFromTeams
            $clubs[] = [
                'transfermarktId' => $transfermarktId,
                'players' => $data['players'] ?? [],
            ];
        }

        $this->line("  Teams: " . count($teamIdMap));
        $this->seedPlayersFromTeams($clubs, $teamIdMap);
    }

    /**
     * Seed teams for Swiss format competitions.
     * Links existing teams by transfermarkt_id (all teams must already exist from their league seeding).
     */
    private function seedSwissFormatTeams(array $clubs, string $competitionId, string $season): array
    {
        $teamIdMap = [];
        $count = 0;

        // Get existing teams by transfermarkt_id
        $teamsByTransfermarktId = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        foreach ($clubs as $club) {
            $transfermarktId = $club['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            $teamId = $teamsByTransfermarktId[$transfermarktId] ?? null;

            if (!$teamId) {
                $this->warn("  Team not found for transfermarkt_id {$transfermarktId}: {$club['name']}");
                continue;
            }

            $teamIdMap[$transfermarktId] = $teamId;

            // Link team to competition
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => $competitionId,
                    'team_id' => $teamId,
                    'season' => $season,
                ],
                [
                    'entry_round' => 1,
                ]
            );

            $count++;
        }

        $this->line("  Teams: {$count}");

        return $teamIdMap;
    }

    private function seedCompetitionRecord(string $code, array $data, int $tier, string $type, string $handler, string $country, string $role = 'foreign'): void
    {
        $season = $data['seasonID'] ?? '2025';
        $scope = ($role === 'european') ? 'continental' : 'domestic';

        DB::table('competitions')->updateOrInsert(
            ['id' => $code],
            [
                'name' => $data['name'],
                'country' => $country,
                'tier' => $tier,
                'type' => $type,
                'role' => $role,
                'scope' => $scope,
                'handler_type' => $handler,
                'season' => $season,
            ]
        );

        $this->line("  Competition: {$data['name']} ({$role})");
    }

    /**
     * Seed teams and return mapping of transfermarktId -> UUID.
     */
    private function seedTeams(array $clubs, string $competitionId, string $season, string $country = 'ES'): array
    {
        $teamIdMap = [];
        $count = 0;

        foreach ($clubs as $club) {
            // Try to get transfermarktId from club data, or extract from image URL
            $transfermarktId = $club['transfermarktId'] ?? $this->extractTransfermarktIdFromImage($club['image'] ?? '');
            if (!$transfermarktId) {
                $this->warn("  Skipping club without transfermarktId: {$club['name']}");
                continue;
            }

            // Check if team already exists
            $existingTeam = DB::table('teams')
                ->where('transfermarkt_id', $transfermarktId)
                ->first();

            $colors = TeamColors::get($club['name']);

            if ($existingTeam) {
                $teamId = $existingTeam->id;
                // Update colors for existing teams
                DB::table('teams')->where('id', $teamId)->update([
                    'colors' => json_encode($colors),
                ]);
            } else {
                $teamId = Str::uuid()->toString();

                // Parse stadium seats
                $stadiumSeats = isset($club['stadiumSeats'])
                    ? (int) str_replace(['.', ','], '', $club['stadiumSeats'])
                    : 0;

                DB::table('teams')->insert([
                    'id' => $teamId,
                    'transfermarkt_id' => $transfermarktId,
                    'name' => $club['name'],
                    'country' => $country,
                    'image' => $club['image'] ?? null,
                    'stadium_name' => $club['stadiumName'] ?? null,
                    'stadium_seats' => $stadiumSeats,
                    'colors' => json_encode($colors),
                ]);
            }

            $teamIdMap[$transfermarktId] = $teamId;

            // Link team to competition
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => $competitionId,
                    'team_id' => $teamId,
                    'season' => $season,
                ],
                []
            );

            $count++;
        }

        $this->line("  Teams: {$count}");

        return $teamIdMap;
    }

    /**
     * Seed players from embedded team data.
     */
    private function seedPlayersFromTeams(array $clubs, array $teamIdMap): void
    {
        $count = 0;
        $valuationService = app(PlayerValuationService::class);

        foreach ($clubs as $club) {
            $transfermarktId = $club['transfermarktId'] ?? $this->extractTransfermarktIdFromImage($club['image'] ?? '');
            if (!$transfermarktId || !isset($teamIdMap[$transfermarktId])) {
                continue;
            }

            $players = $club['players'] ?? [];

            foreach ($players as $player) {
                // Parse date of birth
                $dateOfBirth = null;
                $age = null;
                if (!empty($player['dateOfBirth'])) {
                    try {
                        $dob = Carbon::parse($player['dateOfBirth']);
                        $dateOfBirth = $dob->toDateString();
                        $age = $dob->age;
                    } catch (\Exception $e) {
                        // Ignore invalid dates
                    }
                }

                // Normalize foot value
                $foot = match (strtolower($player['foot'] ?? '')) {
                    'left' => 'left',
                    'right' => 'right',
                    'both' => 'both',
                    default => null,
                };

                // Calculate abilities from market value, position, and age
                $marketValueCents = Money::parseMarketValue($player['marketValue'] ?? null);
                $position = $player['position'] ?? 'Central Midfield';
                [$technical, $physical] = $valuationService->marketValueToAbilities($marketValueCents, $position, $age ?? 25);

                // Insert or update player (never change the id on update)
                $playerValues = [
                    'name' => $player['name'],
                    'date_of_birth' => $dateOfBirth,
                    'nationality' => json_encode($player['nationality'] ?? []),
                    'height' => $player['height'] ?? null,
                    'foot' => $foot,
                    'technical_ability' => $technical,
                    'physical_ability' => $physical,
                ];

                $exists = DB::table('players')
                    ->where('transfermarkt_id', $player['id'])
                    ->exists();

                if ($exists) {
                    DB::table('players')
                        ->where('transfermarkt_id', $player['id'])
                        ->update($playerValues);
                } else {
                    DB::table('players')->insert(array_merge(
                        ['id' => Str::uuid()->toString(), 'transfermarkt_id' => $player['id']],
                        $playerValues
                    ));
                }

                $count++;
            }
        }

        $this->line("  Players: {$count}");
    }

    private function seedCupTeams(array $clubs, string $competitionId, string $season, string $country = 'ES'): void
    {
        $count = 0;

        // Get existing teams by transfermarkt_id
        $teamsByTransfermarktId = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        // Get supercup teams for entry round calculation (config-driven)
        $supercupTeamIds = [];
        $supercupEntryRound = 3;
        $countryConfig = app(CountryConfig::class);
        $supercupConfig = $countryConfig->supercup($country);

        if ($supercupConfig && $supercupConfig['cup'] === $competitionId) {
            $supercupEntryRound = $supercupConfig['cup_entry_round'] ?? 3;
            $supercupTeamIds = DB::table('competition_teams')
                ->join('teams', 'competition_teams.team_id', '=', 'teams.id')
                ->where('competition_teams.competition_id', $supercupConfig['competition'])
                ->where('competition_teams.season', $season)
                ->whereNotNull('teams.transfermarkt_id')
                ->pluck('teams.transfermarkt_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        foreach ($clubs as $club) {
            $cupTeamId = $club['id'];

            // Determine entry round (supercup teams enter later)
            $entryRound = in_array($cupTeamId, $supercupTeamIds) ? $supercupEntryRound : 1;

            // Find or create team
            $teamId = $teamsByTransfermarktId[$cupTeamId] ?? null;

            if (!$teamId) {
                $teamId = Str::uuid()->toString();
                DB::table('teams')->insert([
                    'id' => $teamId,
                    'transfermarkt_id' => (int) $cupTeamId,
                    'name' => $club['name'],
                    'country' => $country,
                    'image' => "https://tmssl.akamaized.net/images/wappen/big/{$cupTeamId}.png",
                ]);
                $teamsByTransfermarktId[$cupTeamId] = $teamId;
            }

            // Link team to cup competition
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => $competitionId,
                    'team_id' => $teamId,
                    'season' => $season,
                ],
                [
                    'entry_round' => $entryRound,
                ]
            );

            $count++;
        }

        $this->line("  Cup teams: {$count}");
    }

    private function loadJson(string $path): array
    {
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return [];
        }

        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }

    /**
     * Extract transfermarkt ID from image URL.
     * URL format: https://tmssl.akamaized.net/images/wappen/big/{id}.png
     */
    private function extractTransfermarktIdFromImage(string $imageUrl): ?string
    {
        if (preg_match('/\/(\d+)\.png$/', $imageUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function generateGamePlayerTemplates(array $countryCodes): void
    {
        $this->newLine();
        $this->info('Generating game player templates...');

        $templateService = app(GamePlayerTemplateService::class);
        $season = '2025';
        $totalCount = 0;

        foreach ($countryCodes as $countryCode) {
            $count = $templateService->generateTemplates($season, $countryCode);
            $this->line("  {$countryCode}: {$count} player templates");
            $totalCount += $count;
        }

        $this->info("Total templates: {$totalCount}");
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->line('  Competitions: ' . DB::table('competitions')->count());
        $this->line('  Teams: ' . DB::table('teams')->count());
        $this->line('  Players: ' . DB::table('players')->count());
        $this->line('  Competition-Team links: ' . DB::table('competition_teams')->count());
        $this->line('  Game player templates: ' . DB::table('game_player_templates')->count());
        $this->newLine();
        $this->info('Reference data seeded successfully!');
    }
}
