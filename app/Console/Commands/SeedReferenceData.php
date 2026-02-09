<?php

namespace App\Console\Commands;

use App\Game\Services\SwissDrawService;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ClubProfilesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SeedReferenceData extends Command
{
    protected $signature = 'app:seed-reference-data
                            {--fresh : Clear existing data before seeding}
                            {--profile=production : Profile to seed (production, test)}';

    protected $description = 'Seed teams, competitions, fixtures, and players from 2025 season JSON data files';

    private array $profiles = [
        'production' => [
            // Spanish leagues (selectable)
            [
                'code' => 'ESP1',
                'path' => 'data/2025/ESP1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'ES',
                'role' => 'primary',
            ],
            [
                'code' => 'ESP2',
                'path' => 'data/2025/ESP2',
                'tier' => 2,
                'handler' => 'league_with_playoff',
                'country' => 'ES',
                'role' => 'primary',
            ],
            // Spanish cups (auto-entered)
            [
                'code' => 'ESPSUP',
                'path' => 'data/2025/ESPSUP',
                'tier' => 0,
                'handler' => 'knockout_cup',
                'country' => 'ES',
                'role' => 'domestic_cup',
            ],
            [
                'code' => 'ESPCUP',
                'path' => 'data/2025/ESPCUP',
                'tier' => 0,
                'handler' => 'knockout_cup',
                'country' => 'ES',
                'role' => 'domestic_cup',
            ],
            // Foreign leagues (scouting/transfers only)
            [
                'code' => 'ENG1',
                'name' => 'Premier League',
                'path' => 'data/2025/ENG1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'GB',
                'role' => 'foreign',
            ],
            [
                'code' => 'DEU1',
                'name' => 'Bundesliga',
                'path' => 'data/2025/DEU1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'DE',
                'role' => 'foreign',
            ],
            [
                'code' => 'FRA1',
                'name' => 'Ligue 1',
                'path' => 'data/2025/FRA1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'FR',
                'role' => 'foreign',
            ],
            [
                'code' => 'ITA1',
                'name' => 'Serie A',
                'path' => 'data/2025/ITA1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'IT',
                'role' => 'foreign',
            ],
            [
                'code' => 'NLD1',
                'name' => 'Eredivisie',
                'path' => 'data/2025/NLD1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'NL',
                'role' => 'foreign',
            ],
            [
                'code' => 'POR1',
                'name' => 'Primeira Liga',
                'path' => 'data/2025/POR1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'PT',
                'role' => 'foreign',
            ],
            [
                'code' => 'EUR',
                'name' => 'Europa',
                'path' => 'data/2025/EUR',
                'tier' => 1,
                'handler' => 'team_pool',
                'country' => 'EU',
                'role' => 'foreign',
            ],
            [
                'code' => 'UCL',
                'path' => 'data/2025/UCL',
                'tier' => 0,
                'handler' => 'swiss_format',
                'country' => 'EU',
                'role' => 'european',
            ],
        ],
        'test' => [
            [
                'code' => 'TEST1',
                'path' => 'data/2025/TEST1',
                'tier' => 1,
                'handler' => 'league',
                'country' => 'XX',
                'role' => 'primary',
            ],
            [
                'code' => 'TESTCUP',
                'path' => 'data/2025/TESTCUP',
                'tier' => 0,
                'handler' => 'knockout_cup',
                'country' => 'XX',
                'role' => 'domestic_cup',
            ],
        ],
    ];

    public function handle(): int
    {
        $profile = $this->option('profile');

        if (!isset($this->profiles[$profile])) {
            $this->error("Unknown profile: {$profile}. Available: " . implode(', ', array_keys($this->profiles)));
            return CommandAlias::FAILURE;
        }

        $this->info("Using profile: {$profile}");

        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        $this->createDefaultUser();

        foreach ($this->profiles[$profile] as $competitionConfig) {
            $this->seedCompetition($competitionConfig);
        }

        // Seed club profiles for all teams
        $this->info('Seeding club profiles...');
        $seeder = new ClubProfilesSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->displaySummary();

        return CommandAlias::SUCCESS;
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
        DB::table('cup_round_templates')->delete();
        DB::table('fixture_templates')->delete();
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

        // Seed fixtures only for primary leagues
        if ($role === 'primary') {
            $fixturesData = $this->loadJson("{$basePath}/fixtures.json");
            if (!empty($fixturesData['matchdays'])) {
                $this->seedFixtures($fixturesData['matchdays'], $code, $seasonId, $teamIdMap);
            }
        } else {
            $this->line("  Fixtures: skipped (foreign league)");
        }
    }

    private function seedCupCompetition(string $basePath, string $code, int $tier, string $handler, string $country, string $role = 'domestic_cup'): void
    {
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $roundsData = $this->loadJson("{$basePath}/rounds.json");
        $matchdaysData = $this->loadJson("{$basePath}/matchdays.json");

        $season = '2025';

        // Seed competition record
        $this->seedCompetitionRecord($code, $teamsData, $tier, 'cup', $handler, $country, $role);

        // Seed cup round templates
        $this->seedCupRoundTemplates($code, $season, $roundsData, $matchdaysData);

        // Seed cup teams (link existing teams to cup)
        $this->seedCupTeams($teamsData['clubs'], $code, $season, $country);
    }

    private function seedSwissFormatCompetition(string $basePath, string $code, int $tier, string $handler, string $country, string $role = 'european'): void
    {
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $roundsData = $this->loadJson("{$basePath}/rounds.json");
        $matchdaysData = $this->loadJson("{$basePath}/matchdays.json");

        $season = $teamsData['seasonID'] ?? '2025';

        // Swiss format uses 'league' type so standings are updated during league phase
        $this->seedCompetitionRecord($code, $teamsData, $tier, 'league', $handler, $country, $role);

        // Seed teams (links existing teams by transfermarkt_id, like cups)
        $teamIdMap = $this->seedSwissFormatTeams($teamsData['clubs'], $code, $season);

        // Seed embedded player data if present (clubs that have a 'players' array)
        $this->seedPlayersFromTeams($teamsData['clubs'], $teamIdMap);

        // Generate league phase fixtures using the Swiss draw algorithm
        $this->seedSwissFormatFixtures($teamsData['clubs'], $code, $season, $teamIdMap);

        // Seed knockout round templates
        $this->seedCupRoundTemplates($code, $season, $roundsData, $matchdaysData);

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

            if (!$teamId) {
                $teamId = Str::uuid()->toString();
                DB::table('teams')->insert([
                    'id' => $teamId,
                    'transfermarkt_id' => $transfermarktId,
                    'name' => $data['name'] ?? "Unknown ({$transfermarktId})",
                    'country' => $country,
                    'image' => $data['image'] ?? null,
                    'stadium_name' => $data['stadiumName'] ?? null,
                    'stadium_seats' => isset($data['stadiumSeats'])
                        ? (int) str_replace(['.', ','], '', $data['stadiumSeats'])
                        : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
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

    /**
     * Generate Swiss format league phase fixtures using the draw algorithm.
     */
    private function seedSwissFormatFixtures(array $clubs, string $competitionId, string $season, array $teamIdMap): void
    {
        // Build team data for the draw service
        $drawTeams = [];
        foreach ($clubs as $club) {
            $clubId = $club['id'] ?? null;
            if (!$clubId || !isset($teamIdMap[$clubId])) {
                continue;
            }

            $drawTeams[] = [
                'id' => $teamIdMap[$clubId],
                'pot' => $club['pot'] ?? 4,
                'country' => $club['country'] ?? 'XX',
            ];
        }

        // Generate fixtures
        $drawService = new SwissDrawService();
        $startDate = Carbon::parse("{$season}-09-17"); // UCL league phase starts mid-September
        $fixtures = $drawService->generateFixtures($drawTeams, $startDate);

        // Group by matchday for seeding
        $matchdays = [];
        foreach ($fixtures as $fixture) {
            $md = $fixture['matchday'];
            if (!isset($matchdays[$md])) {
                $matchdays[$md] = ['matchday' => $md, 'date' => $fixture['date'], 'matches' => []];
            }
            $matchdays[$md]['matches'][] = [
                'homeTeamId' => $fixture['homeTeamId'],
                'awayTeamId' => $fixture['awayTeamId'],
            ];
        }

        // Seed as fixture templates (using team UUIDs directly)
        $count = 0;
        foreach ($matchdays as $matchday) {
            $roundNumber = (int) $matchday['matchday'];
            $scheduledDate = Carbon::createFromFormat('d/m/y', $matchday['date']);

            $matchNumber = 1;
            foreach ($matchday['matches'] as $match) {
                DB::table('fixture_templates')->updateOrInsert(
                    [
                        'competition_id' => $competitionId,
                        'season' => $season,
                        'round_number' => $roundNumber,
                        'home_team_id' => $match['homeTeamId'],
                        'away_team_id' => $match['awayTeamId'],
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'match_number' => $matchNumber,
                        'scheduled_date' => $scheduledDate,
                        'location' => null,
                    ]
                );

                $matchNumber++;
                $count++;
            }
        }

        $this->line("  League phase fixtures: {$count}");
    }

    private function seedCompetitionRecord(string $code, array $data, int $tier, string $type, string $handler, string $country, string $role = 'foreign'): void
    {
        $season = $data['seasonID'] ?? '2025';

        DB::table('competitions')->updateOrInsert(
            ['id' => $code],
            [
                'name' => $data['name'],
                'country' => $country,
                'tier' => $tier,
                'type' => $type,
                'role' => $role,
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

            if ($existingTeam) {
                $teamId = $existingTeam->id;
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
                    'created_at' => now(),
                    'updated_at' => now(),
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
                $marketValueCents = $this->parseMarketValue($player['marketValue'] ?? null);
                $position = $player['position'] ?? 'Central Midfield';
                [$technical, $physical] = $this->calculateAbilities($marketValueCents, $position, $age);

                // Insert player
                DB::table('players')->updateOrInsert(
                    ['transfermarkt_id' => $player['id']],
                    [
                        'id' => Str::uuid()->toString(),
                        'name' => $player['name'],
                        'date_of_birth' => $dateOfBirth,
                        'nationality' => json_encode($player['nationality'] ?? []),
                        'height' => $player['height'] ?? null,
                        'foot' => $foot,
                        'technical_ability' => $technical,
                        'physical_ability' => $physical,
                        'updated_at' => now(),
                    ]
                );

                $count++;
            }
        }

        $this->line("  Players: {$count}");
    }

    private function seedCupRoundTemplates(string $competitionId, string $season, array $rounds, array $matchdays): void
    {
        $count = 0;

        // Build date lookup from matchdays
        $dateLookup = [];
        foreach ($matchdays as $md) {
            $roundNum = $md['round'] ?? 0;
            $date = $md['date'] ?? null;
            $matchdayName = $md['matchday'] ?? '';

            if (!isset($dateLookup[$roundNum])) {
                $dateLookup[$roundNum] = ['first' => null, 'second' => null, 'name' => $matchdayName];
            }

            // Check if this is a second leg
            if (str_contains($matchdayName, 'Vuelta')) {
                $dateLookup[$roundNum]['second'] = $date;
            } elseif ($dateLookup[$roundNum]['first'] === null) {
                $dateLookup[$roundNum]['first'] = $date;
                $dateLookup[$roundNum]['name'] = $matchdayName;
            } else {
                $dateLookup[$roundNum]['second'] = $date;
            }
        }

        foreach ($rounds as $round) {
            $roundNumber = $round['round'];
            $type = $round['type'] === 'two_legged_knockout' ? 'two_leg' : 'one_leg';

            $dates = $dateLookup[$roundNumber] ?? ['first' => null, 'second' => null, 'name' => "Round {$roundNumber}"];
            $roundName = $dates['name'];

            // Clean up round name
            $roundName = preg_replace('/\s*\(Ida\)$/', '', $roundName);

            DB::table('cup_round_templates')->updateOrInsert(
                [
                    'competition_id' => $competitionId,
                    'season' => $season,
                    'round_number' => $roundNumber,
                ],
                [
                    'round_name' => $roundName,
                    'type' => $type,
                    'first_leg_date' => $dates['first'] ? Carbon::parse($dates['first']) : null,
                    'second_leg_date' => $dates['second'] ? Carbon::parse($dates['second']) : null,
                    'teams_entering' => 0,
                ]
            );

            $count++;
        }

        $this->line("  Round templates: {$count}");
    }

    private function seedCupTeams(array $clubs, string $competitionId, string $season, string $country = 'ES'): void
    {
        $count = 0;

        // Get existing teams by transfermarkt_id
        $teamsByTransfermarktId = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        // Get Supercopa teams for entry round calculation
        $supercopaTeamIds = [];
        if ($competitionId === 'ESPCUP') {
            $supercopaTeamIds = DB::table('competition_teams')
                ->join('teams', 'competition_teams.team_id', '=', 'teams.id')
                ->where('competition_teams.competition_id', 'ESPSUP')
                ->where('competition_teams.season', $season)
                ->whereNotNull('teams.transfermarkt_id')
                ->pluck('teams.transfermarkt_id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        foreach ($clubs as $club) {
            $cupTeamId = $club['id'];

            // Determine entry round
            $entryRound = in_array($cupTeamId, $supercopaTeamIds) ? 3 : 1;

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
                    'created_at' => now(),
                    'updated_at' => now(),
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

        // Update teams_entering counts
        $this->updateTeamsEnteringCounts($competitionId, $season);

        $this->line("  Cup teams: {$count}");
    }

    private function updateTeamsEnteringCounts(string $competitionId, string $season): void
    {
        $entryCounts = DB::table('competition_teams')
            ->where('competition_id', $competitionId)
            ->where('season', $season)
            ->selectRaw('entry_round, COUNT(*) as count')
            ->groupBy('entry_round')
            ->pluck('count', 'entry_round')
            ->toArray();

        foreach ($entryCounts as $round => $count) {
            DB::table('cup_round_templates')
                ->where('competition_id', $competitionId)
                ->where('season', $season)
                ->where('round_number', $round)
                ->update(['teams_entering' => $count]);
        }
    }

    /**
     * Seed fixtures from the new matchdays format.
     */
    private function seedFixtures(array $matchdays, string $competitionId, string $season, array $teamIdMap): void
    {
        $count = 0;

        foreach ($matchdays as $matchday) {
            $roundNumber = (int) ($matchday['matchday'] ?? 0);

            if ($roundNumber === 0) {
                $this->warn("  Skipping matchday with no round number");
                continue;
            }

            // Parse date (format: dd/mm/yy)
            $dateStr = $matchday['date'] ?? null;
            $scheduledDate = null;
            if ($dateStr) {
                try {
                    $scheduledDate = Carbon::createFromFormat('d/m/y', $dateStr);
                } catch (\Exception $e) {
                    $this->warn("  Could not parse date: {$dateStr}");
                }
            }

            $matches = $matchday['matches'] ?? [];
            $matchNumber = 1;

            foreach ($matches as $match) {
                $homeTransfermarktId = $match['homeTeamId'] ?? null;
                $awayTransfermarktId = $match['awayTeamId'] ?? null;

                if (!$homeTransfermarktId || !$awayTransfermarktId) {
                    continue;
                }

                $homeTeamId = $teamIdMap[$homeTransfermarktId] ?? null;
                $awayTeamId = $teamIdMap[$awayTransfermarktId] ?? null;

                if (!$homeTeamId || !$awayTeamId) {
                    $this->warn("  Team not found: home={$homeTransfermarktId}, away={$awayTransfermarktId}");
                    continue;
                }

                DB::table('fixture_templates')->updateOrInsert(
                    [
                        'competition_id' => $competitionId,
                        'season' => $season,
                        'round_number' => $roundNumber,
                        'home_team_id' => $homeTeamId,
                        'away_team_id' => $awayTeamId,
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'match_number' => $matchNumber,
                        'scheduled_date' => $scheduledDate,
                        'location' => null,
                    ]
                );

                $matchNumber++;
                $count++;
            }
        }

        $this->line("  Fixtures: {$count}");
    }

    private function parseMarketValue(?string $value): int
    {
        if (!$value) {
            return 0;
        }

        $value = trim(str_replace(['€', ' '], '', $value));

        if (preg_match('/^([\d.]+)(k|m)?$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $multiplier = strtolower($matches[2] ?? '');

            return match ($multiplier) {
                'm' => (int) ($number * 1_000_000 * 100),
                'k' => (int) ($number * 1_000 * 100),
                default => (int) ($number * 100),
            };
        }

        return 0;
    }

    private function calculateAbilities(int $marketValueCents, string $position, ?int $age): array
    {
        $age = $age ?? 25;

        $rawAbility = $this->marketValueToRawAbility($marketValueCents);
        $baseAbility = $this->adjustAbilityForAge($rawAbility, $marketValueCents, $age);

        $technicalRatio = match ($position) {
            'Goalkeeper' => 0.55,
            'Centre-Back' => 0.35,
            'Left-Back', 'Right-Back' => 0.45,
            'Defensive Midfield' => 0.45,
            'Central Midfield' => 0.55,
            'Left Midfield', 'Right Midfield' => 0.55,
            'Attacking Midfield' => 0.70,
            'Left Winger', 'Right Winger' => 0.65,
            'Second Striker' => 0.70,
            'Centre-Forward' => 0.65,
            default => 0.50,
        };

        $variance = rand(2, 5);
        $technical = (int) round($baseAbility + ($technicalRatio - 0.5) * $variance * 2);
        $physical = (int) round($baseAbility + (0.5 - $technicalRatio) * $variance * 2);

        if ($age > 33) {
            $physical = (int) round($physical * 0.92);
        } elseif ($age > 30) {
            $physical = (int) round($physical * 0.96);
        }

        $technical = max(30, min(99, $technical));
        $physical = max(30, min(99, $physical));

        return [$technical, $physical];
    }

    private function marketValueToRawAbility(int $marketValueCents): int
    {
        return match (true) {
            $marketValueCents >= 10_000_000_000 => rand(88, 95),
            $marketValueCents >= 5_000_000_000 => rand(83, 90),
            $marketValueCents >= 2_000_000_000 => rand(78, 85),
            $marketValueCents >= 1_000_000_000 => rand(73, 80),
            $marketValueCents >= 500_000_000 => rand(68, 75),
            $marketValueCents >= 200_000_000 => rand(63, 70),
            $marketValueCents >= 100_000_000 => rand(58, 65),
            $marketValueCents > 0 => rand(50, 60),
            default => rand(45, 55),
        };
    }

    private function adjustAbilityForAge(int $rawAbility, int $marketValueCents, int $age): int
    {
        if ($age < 23) {
            // Base cap increases with age: 17yo = 75, 22yo = 85
            $ageCap = 75 + ($age - 17) * 2;

            // Exceptional market value raises the cap significantly
            // A €200M teenager is already world-class, not just "promising"
            if ($marketValueCents >= 15_000_000_000) { // €150M+ (generational talent)
                $ageCap += 14;
            } elseif ($marketValueCents >= 10_000_000_000) { // €100M+
                $ageCap += 10;
            } elseif ($marketValueCents >= 5_000_000_000) { // €50M+
                $ageCap += 6;
            } elseif ($marketValueCents >= 2_000_000_000) { // €20M+
                $ageCap += 3;
            }

            return min($rawAbility, $ageCap);
        }

        if ($age <= 30) {
            return $rawAbility;
        }

        $typicalValueForAge = match (true) {
            $age <= 32 => 500_000_000,
            $age <= 34 => 300_000_000,
            $age <= 36 => 150_000_000,
            default => 80_000_000,
        };

        $valueRatio = $marketValueCents / max(1, $typicalValueForAge);

        $abilityBoost = match (true) {
            $valueRatio >= 10 => 12,
            $valueRatio >= 5 => 8,
            $valueRatio >= 3 => 5,
            $valueRatio >= 2 => 3,
            $valueRatio >= 1 => 1,
            default => 0,
        };

        return min(95, $rawAbility + $abilityBoost);
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

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->line('  Competitions: ' . DB::table('competitions')->count());
        $this->line('  Teams: ' . DB::table('teams')->count());
        $this->line('  Players: ' . DB::table('players')->count());
        $this->line('  Competition-Team links: ' . DB::table('competition_teams')->count());
        $this->line('  Fixture templates: ' . DB::table('fixture_templates')->count());
        $this->line('  Cup round templates: ' . DB::table('cup_round_templates')->count());
        $this->newLine();
        $this->info('Reference data seeded successfully!');
    }
}
