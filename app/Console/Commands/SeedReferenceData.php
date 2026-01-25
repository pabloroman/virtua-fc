<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedReferenceData extends Command
{
    protected $signature = 'app:seed-reference-data {--fresh : Clear existing data before seeding}';

    protected $description = 'Seed teams, competitions, fixtures, and players from JSON data files';

    private array $competitionsToSeed = [
        ['code' => 'ESP1', 'path' => 'data/ESP1/2024', 'tier' => 1, 'type' => 'league'],
        ['code' => 'ESP2', 'path' => 'data/ESP2/2024', 'tier' => 2, 'type' => 'league'],
        ['code' => 'ESPCUP', 'path' => 'data/transfermarkt/ESPCUP/2024', 'tier' => 0, 'type' => 'cup'],
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        $this->createDefaultUser();

        foreach ($this->competitionsToSeed as $competitionConfig) {
            $this->seedCompetition($competitionConfig);
        }

        $this->displaySummary();

        return Command::SUCCESS;
    }

    private function createDefaultUser(): void
    {
        $user = User::firstOrCreate(
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

        // Clear game-scoped tables first (they reference both games and reference tables)
        DB::table('game_players')->delete();
        DB::table('match_events')->delete();
        DB::table('cup_ties')->delete();

        // Clear other game-scoped tables
        DB::table('game_standings')->delete();
        DB::table('game_matches')->delete();
        DB::table('games')->delete();

        // Clear reference tables in correct order
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
        $type = $config['type'] ?? 'league';

        $this->info("Seeding {$code}...");

        if ($type === 'cup') {
            $this->seedCupCompetition($basePath, $code, $tier);
        } else {
            $this->seedLeagueCompetition($basePath, $code, $tier);
        }
    }

    private function seedLeagueCompetition(string $basePath, string $code, int $tier): void
    {
        // Load competition metadata
        $competitionData = $this->loadJson("{$basePath}/competition.json");
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $fixturesData = $this->loadJson("{$basePath}/fixtures.json");

        // Seed competition
        $this->seedCompetitionRecord($code, $competitionData, $tier, 'league');

        // Seed teams
        $this->seedTeams($teamsData['clubs'], $code, $competitionData['seasonID']);

        // Seed players
        $this->seedPlayers($basePath, $teamsData['clubs']);

        // Seed fixtures
        $this->seedFixtures($fixturesData['matchdays'], $code, $competitionData['seasonID']);
    }

    private function seedCupCompetition(string $basePath, string $code, int $tier): void
    {
        // Load cup data
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $roundsData = $this->loadJson("{$basePath}/rounds.json");
        $matchdaysData = $this->loadJson("{$basePath}/matchdays.json");

        $season = $teamsData['seasonID'] ?? '2024';
        $name = $teamsData['name'] ?? 'Copa del Rey';

        // Seed competition record
        DB::table('competitions')->updateOrInsert(
            ['id' => $code],
            [
                'name' => $name,
                'country' => 'ES',
                'tier' => $tier,
                'type' => 'cup',
                'season' => $season,
            ]
        );

        $this->line("  Competition: {$name}");

        // Seed cup round templates
        $this->seedCupRoundTemplates($code, $season, $roundsData, $matchdaysData);

        // Seed teams with entry rounds (no fixtures for cups - draws happen dynamically)
        $this->seedCupTeams($teamsData['clubs'], $code, $season);
    }

    private function seedCompetitionRecord(string $code, array $data, int $tier, string $type = 'league'): void
    {
        DB::table('competitions')->updateOrInsert(
            ['id' => $code],
            [
                'name' => $data['name'],
                'country' => 'ES',
                'tier' => $tier,
                'type' => $type,
                'season' => $data['seasonID'],
            ]
        );

        $this->line("  Competition: {$data['name']}");
    }

    private function seedTeams(array $clubs, string $competitionId, string $season): void
    {
        $count = 0;

        foreach ($clubs as $club) {
            // Parse stadium seats (remove dots from Spanish number format)
            $stadiumSeats = isset($club['stadiumSeats'])
                ? (int) str_replace('.', '', $club['stadiumSeats'])
                : 0;

            // Parse founded date
            $foundedOn = null;
            if (!empty($club['foundedOn'])) {
                try {
                    $foundedOn = Carbon::parse($club['foundedOn'])->toDateString();
                } catch (\Exception $e) {
                    // Ignore invalid dates
                }
            }

            // Insert or update team
            DB::table('teams')->updateOrInsert(
                ['id' => $club['id']],
                [
                    'transfermarkt_id' => $club['transfermarktId'] ?? null,
                    'name' => $club['name'],
                    'official_name' => $club['officialName'] ?? null,
                    'country' => 'ES',
                    'image' => $club['image'] ?? null,
                    'stadium_name' => $club['stadiumName'] ?? null,
                    'stadium_seats' => $stadiumSeats,
                    'colors' => isset($club['colors']) ? json_encode($club['colors']) : null,
                    'current_market_value' => $club['currentMarketValue'] ?? null,
                    'founded_on' => $foundedOn,
                    'updated_at' => now(),
                ]
            );

            // Link team to competition
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => $competitionId,
                    'team_id' => $club['id'],
                    'season' => $season,
                ],
                []
            );

            $count++;
        }

        $this->line("  Teams: {$count}");
    }

    private function seedPlayers(string $basePath, array $clubs): void
    {
        $playersPath = "{$basePath}/players";
        $count = 0;

        if (!is_dir($playersPath)) {
            $this->warn("  No players directory found at {$playersPath}");
            return;
        }

        foreach ($clubs as $club) {
            $transfermarktId = $club['transfermarktId'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            $playerFile = "{$playersPath}/{$transfermarktId}.json";
            if (!file_exists($playerFile)) {
                $this->warn("  No player file for team {$club['name']}");
                continue;
            }

            $playerData = $this->loadJson($playerFile);
            $players = $playerData['players'] ?? [];

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

                // Insert player (biographical data + base abilities)
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

        // Build a map of matchday names to dates
        $dateLookup = [];
        foreach ($matchdays as $md) {
            $roundNum = $md['round'] ?? 0;
            $date = $md['date'] ?? null;
            $matchdayName = $md['matchday'] ?? '';

            if (!isset($dateLookup[$roundNum])) {
                $dateLookup[$roundNum] = ['first' => null, 'second' => null, 'name' => $matchdayName];
            }

            // Check if this is a second leg (contains "Vuelta" or is the second entry for same round)
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

            // Clean up round name (remove "(Ida)" suffix if present)
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
                    'teams_entering' => 0, // Will be calculated based on teams
                ]
            );

            $count++;
        }

        $this->line("  Round templates: {$count}");
    }

    private function seedCupTeams(array $clubs, string $competitionId, string $season): void
    {
        $count = 0;

        // Build lookup of existing teams by transfermarkt_id
        // The cup JSON uses numeric IDs which are transfermarkt IDs
        $teamsByTransfermarktId = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        // Get ESP1 and ESP2 team transfermarkt IDs
        $esp1TransfermarktIds = DB::table('teams')
            ->join('competition_teams', 'teams.id', '=', 'competition_teams.team_id')
            ->where('competition_teams.competition_id', 'ESP1')
            ->where('competition_teams.season', $season)
            ->whereNotNull('teams.transfermarkt_id')
            ->pluck('teams.transfermarkt_id')
            ->toArray();

        $esp2TransfermarktIds = DB::table('teams')
            ->join('competition_teams', 'teams.id', '=', 'competition_teams.team_id')
            ->where('competition_teams.competition_id', 'ESP2')
            ->where('competition_teams.season', $season)
            ->whereNotNull('teams.transfermarkt_id')
            ->pluck('teams.transfermarkt_id')
            ->toArray();

        foreach ($clubs as $club) {
            $cupTeamId = $club['id']; // This is the transfermarkt ID

            // Determine entry round:
            // - La Liga (ESP1) teams enter at round 3 (Dieciseisavos)
            // - Segunda (ESP2) teams enter at round 2
            // - Lower league teams enter at round 1
            $entryRound = 1; // Default: lower leagues
            if (in_array($cupTeamId, $esp1TransfermarktIds)) {
                $entryRound = 3;
            } elseif (in_array($cupTeamId, $esp2TransfermarktIds)) {
                $entryRound = 2;
            }

            // Find existing team by transfermarkt_id, or create new one
            $teamId = $teamsByTransfermarktId[$cupTeamId] ?? null;

            if (!$teamId) {
                // Team doesn't exist in our system - create it
                $teamId = Str::uuid()->toString();
                DB::table('teams')->insert([
                    'id' => $teamId,
                    'transfermarkt_id' => (int) $cupTeamId,
                    'name' => $club['name'],
                    'country' => 'ES',
                    'updated_at' => now(),
                ]);
            }

            // Link team to cup competition with entry round
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

        // Update teams_entering count for each round
        $this->updateTeamsEnteringCounts($competitionId, $season);

        $this->line("  Cup teams: {$count}");
    }

    private function updateTeamsEnteringCounts(string $competitionId, string $season): void
    {
        // Count teams entering at each round
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

    private function seedFixtures(array $matchdays, string $competitionId, string $season): void
    {
        $count = 0;

        foreach ($matchdays as $matchday) {
            // Handle both integer and string matchday values
            $roundNumber = (int) ($matchday['matchday'] ?? $matchday['RoundNumber'] ?? 0);

            if ($roundNumber === 0) {
                $this->warn("  Skipping matchday with no round number");
                continue;
            }

            foreach ($matchday['fixtures'] as $fixture) {
                DB::table('fixture_templates')->updateOrInsert(
                    [
                        'competition_id' => $competitionId,
                        'season' => $season,
                        'round_number' => $roundNumber,
                        'home_team_id' => $fixture['HomeTeam'],
                        'away_team_id' => $fixture['AwayTeam'],
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'match_number' => $fixture['MatchNumber'] ?? null,
                        'scheduled_date' => Carbon::parse($fixture['DateUtc']),
                        'location' => $fixture['Location'] ?? null,
                    ]
                );

                $count++;
            }
        }

        $this->line("  Fixtures: {$count}");
    }

    /**
     * Parse market value string to cents.
     * Examples: "€45.00m" -> 4500000000, "€800k" -> 80000000, "€2.50m" -> 250000000
     */
    private function parseMarketValue(?string $value): int
    {
        if (!$value) {
            return 0;
        }

        // Remove currency symbol and whitespace
        $value = trim(str_replace(['€', ' '], '', $value));

        // Extract number and multiplier
        if (preg_match('/^([\d.]+)(k|m)?$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $multiplier = strtolower($matches[2] ?? '');

            $cents = match ($multiplier) {
                'm' => (int) ($number * 1_000_000 * 100), // millions to cents
                'k' => (int) ($number * 1_000 * 100),     // thousands to cents
                default => (int) ($number * 100),         // raw value to cents
            };

            return $cents;
        }

        return 0;
    }

    /**
     * Calculate technical and physical abilities from market value, position, and age.
     *
     * @return array{0: int, 1: int} [technical_ability, physical_ability]
     */
    private function calculateAbilities(int $marketValueCents, string $position, ?int $age): array
    {
        // Base ability from market value tier (0-100 scale)
        $baseAbility = match (true) {
            $marketValueCents >= 10_000_000_000 => rand(88, 95), // €100M+
            $marketValueCents >= 5_000_000_000 => rand(83, 90),  // €50-100M
            $marketValueCents >= 2_000_000_000 => rand(78, 85),  // €20-50M
            $marketValueCents >= 1_000_000_000 => rand(73, 80),  // €10-20M
            $marketValueCents >= 500_000_000 => rand(68, 75),    // €5-10M
            $marketValueCents >= 200_000_000 => rand(63, 70),    // €2-5M
            $marketValueCents >= 100_000_000 => rand(58, 65),    // €1-2M
            $marketValueCents > 0 => rand(50, 60),               // Under €1M
            default => rand(45, 55),                              // Unknown value
        };

        // Position-based split (technical vs physical emphasis)
        // Higher ratio = more technical, lower = more physical
        $technicalRatio = match ($position) {
            'Goalkeeper' => 0.50,
            'Centre-Back' => 0.40,
            'Left-Back', 'Right-Back' => 0.45,
            'Defensive Midfield' => 0.50,
            'Central Midfield' => 0.55,
            'Left Midfield', 'Right Midfield' => 0.55,
            'Attacking Midfield' => 0.65,
            'Left Winger', 'Right Winger' => 0.60,
            'Second Striker' => 0.60,
            'Centre-Forward' => 0.55,
            default => 0.50,
        };

        // Apply variance: one attribute slightly higher, one slightly lower
        $variance = rand(2, 5);
        $technical = (int) round($baseAbility + ($technicalRatio - 0.5) * $variance * 2);
        $physical = (int) round($baseAbility + (0.5 - $technicalRatio) * $variance * 2);

        // Age adjustment for physical ability (peaks at 27, declines after 30)
        if ($age !== null) {
            if ($age < 23) {
                // Young players: slightly lower physical (still developing)
                $physical = (int) round($physical * 0.95);
            } elseif ($age > 33) {
                // Veterans: significant physical decline
                $physical = (int) round($physical * 0.85);
            } elseif ($age > 30) {
                // Early decline
                $physical = (int) round($physical * 0.92);
            }
            // 23-30: peak physical years, no adjustment
        }

        // Clamp to 0-100
        $technical = max(30, min(99, $technical));
        $physical = max(30, min(99, $physical));

        return [$technical, $physical];
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
