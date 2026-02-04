<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SeedReferenceData extends Command
{
    protected $signature = 'app:seed-reference-data {--fresh : Clear existing data before seeding}';

    protected $description = 'Seed teams, competitions, fixtures, and players from JSON data files';

    private array $competitionsToSeed = [
        [
            'code' => 'ESP1',
            'path' => 'data/ESP1/2024',
            'tier' => 1,
            'handler' => 'league',
            'minimum_annual_wage' => 20_000_000, // €200,000 in cents (La Liga minimum)
        ],
        [
            'code' => 'ESP2',
            'path' => 'data/ESP2/2024',
            'tier' => 2,
            'handler' => 'league_with_playoff',
            'minimum_annual_wage' => 10_000_000, // €100,000 in cents (La Liga 2 minimum)
        ],
        [
            'code' => 'ESPSUP',
            'path' => 'data/transfermarkt/ESPSUP/2024',
            'tier' => 0,
            'handler' => 'knockout_cup',
            'minimum_annual_wage' => null, // Cups don't have minimums; use team's league minimum
        ],
        [
            'code' => 'ESPCUP',
            'path' => 'data/transfermarkt/ESPCUP/2024',
            'tier' => 0,
            'handler' => 'knockout_cup',
            'minimum_annual_wage' => null, // Cups don't have minimums; use team's league minimum
        ],
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

        return CommandAlias::SUCCESS;
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
        $handler = $config['handler'] ?? 'league';
        $minimumAnnualWage = $config['minimum_annual_wage'] ?? null;

        // Derive competition type from handler
        $isCup = in_array($handler, ['knockout_cup', 'group_stage_cup']);

        $this->info("Seeding {$code}...");

        if ($isCup) {
            $this->seedCupCompetition($basePath, $code, $tier, $handler, $minimumAnnualWage);
        } else {
            $this->seedLeagueCompetition($basePath, $code, $tier, $handler, $minimumAnnualWage);
        }
    }

    private function seedLeagueCompetition(string $basePath, string $code, int $tier, string $handler, ?int $minimumAnnualWage): void
    {
        // Load competition metadata
        $competitionData = $this->loadJson("{$basePath}/competition.json");
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $fixturesData = $this->loadJson("{$basePath}/fixtures.json");

        // Seed competition
        $this->seedCompetitionRecord($code, $competitionData, $tier, 'league', $handler, $minimumAnnualWage);

        // Seed teams
        $this->seedTeams($teamsData['clubs'], $code, $competitionData['seasonID']);

        // Seed players
        $this->seedPlayers($basePath, $teamsData['clubs']);

        // Seed fixtures
        $this->seedFixtures($fixturesData['matchdays'], $code, $competitionData['seasonID']);
    }

    private function seedCupCompetition(string $basePath, string $code, int $tier, string $handler, ?int $minimumAnnualWage): void
    {
        // Load cup data
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $roundsData = $this->loadJson("{$basePath}/rounds.json");
        $matchdaysData = $this->loadJson("{$basePath}/matchdays.json");

        $season = $teamsData['seasonID'] ?? '2024';

        // Seed competition record
        $this->seedCompetitionRecord($code, $teamsData, $tier, 'cup', $handler, $minimumAnnualWage);

        // Seed cup round templates
        $this->seedCupRoundTemplates($code, $season, $roundsData, $matchdaysData);

        // Seed teams with entry rounds (no fixtures for cups - draws happen dynamically)
        $this->seedCupTeams($teamsData['clubs'], $code, $season);
    }

    private function seedCompetitionRecord(string $code, array $data, int $tier, string $type, string $handler, ?int $minimumAnnualWage): void
    {
        DB::table('competitions')->updateOrInsert(
            ['id' => $code],
            [
                'name' => $data['name'],
                'country' => 'ES',
                'tier' => $tier,
                'type' => $type,
                'handler_type' => $handler,
                'season' => $data['seasonID'],
                'minimum_annual_wage' => $minimumAnnualWage,
            ]
        );

        $wageDisplay = $minimumAnnualWage
            ? '€' . number_format($minimumAnnualWage / 100, 0, ',', '.') . '/year min'
            : 'no minimum (cup)';
        $this->line("  Competition: {$data['name']} ({$wageDisplay})");
    }

    private function seedTeams(array $clubs, string $competitionId, string $season): void
    {
        $count = 0;

        foreach ($clubs as $club) {
            // Parse stadium seats (remove dots from Spanish number format)
            $stadiumSeats = isset($club['stadiumSeats'])
                ? (int) str_replace('.', '', $club['stadiumSeats'])
                : 0;

            // Insert or update team
            DB::table('teams')->updateOrInsert(
                ['id' => $club['id']],
                [
                    'transfermarkt_id' => $club['transfermarktId'] ?? null,
                    'name' => $club['name'],
                    'country' => 'ES',
                    'image' => $club['image'] ?? null,
                    'stadium_name' => $club['stadiumName'] ?? null,
                    'stadium_seats' => $stadiumSeats,
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

        // Supercopa de España teams enter the Copa del Rey at Round 3
        // (they play the Supercopa first, so they join later)
        // Get Supercopa team transfermarkt IDs from the database
        $supercopaTeamIds = DB::table('competition_teams')
            ->join('teams', 'competition_teams.team_id', '=', 'teams.id')
            ->where('competition_teams.competition_id', 'ESPSUP')
            ->where('competition_teams.season', $season)
            ->whereNotNull('teams.transfermarkt_id')
            ->pluck('teams.transfermarkt_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        foreach ($clubs as $club) {
            $cupTeamId = $club['id']; // This is the transfermarkt ID

            // Determine entry round:
            // - Supercopa teams enter at round 3 (they play the Supercopa first)
            // - All other teams enter at round 1
            $entryRound = in_array($cupTeamId, $supercopaTeamIds) ? 3 : 1;

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
                    'image' => "https://tmssl.akamaized.net/images/wappen/big/{$cupTeamId}.png",
                    'updated_at' => now(),
                ]);
            } else {
                // Update existing team's image if not set
                DB::table('teams')
                    ->where('id', $teamId)
                    ->whereNull('image')
                    ->update([
                        'image' => "https://tmssl.akamaized.net/images/wappen/big/{$cupTeamId}.png",
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
     * Key insight: Market value reflects different things at different ages:
     * - Young players (under 23): Value includes "potential premium" - they're not at peak skill yet
     * - Prime players (23-30): Value closely reflects current ability
     * - Veterans (31+): Value is depressed by age, but skill declines slower than value
     *
     * A €15M 36-year-old is exceptional (most are worth €1-3M at that age).
     * A €120M 17-year-old has potential, but isn't at peak ability yet.
     *
     * @return array{0: int, 1: int} [technical_ability, physical_ability]
     */
    private function calculateAbilities(int $marketValueCents, string $position, ?int $age): array
    {
        $age = $age ?? 25; // Default to prime age if unknown

        // Step 1: Get raw ability from market value tier
        $rawAbility = $this->marketValueToRawAbility($marketValueCents);

        // Step 2: Apply age-based adjustments
        $baseAbility = $this->adjustAbilityForAge($rawAbility, $marketValueCents, $age);

        // Step 3: Position-based split (technical vs physical emphasis)
        // Higher ratio = more technical, lower = more physical
        $technicalRatio = match ($position) {
            'Goalkeeper' => 0.55,           // Distribution, handling matter
            'Centre-Back' => 0.35,          // More physical
            'Left-Back', 'Right-Back' => 0.45,
            'Defensive Midfield' => 0.45,
            'Central Midfield' => 0.55,
            'Left Midfield', 'Right Midfield' => 0.55,
            'Attacking Midfield' => 0.70,   // Very technical
            'Left Winger', 'Right Winger' => 0.65,
            'Second Striker' => 0.70,
            'Centre-Forward' => 0.65,       // Elite strikers are technical (finishing, movement)
            default => 0.50,
        };

        // Apply variance: one attribute slightly higher, one slightly lower
        $variance = rand(2, 5);
        $technical = (int) round($baseAbility + ($technicalRatio - 0.5) * $variance * 2);
        $physical = (int) round($baseAbility + (0.5 - $technicalRatio) * $variance * 2);

        // Physical decline for veterans (in addition to age-adjusted ability)
        // This reflects that physical attributes decline faster than technical
        if ($age > 33) {
            $physical = (int) round($physical * 0.92);
        } elseif ($age > 30) {
            $physical = (int) round($physical * 0.96);
        }

        // Clamp to 30-99
        $technical = max(30, min(99, $technical));
        $physical = max(30, min(99, $physical));

        return [$technical, $physical];
    }

    /**
     * Convert market value to a raw ability estimate.
     */
    private function marketValueToRawAbility(int $marketValueCents): int
    {
        return match (true) {
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
    }

    /**
     * Adjust raw ability based on age.
     *
     * Young players: Cap ability (their value includes potential, not current skill)
     * Veterans: Boost ability (their low market value is due to age, not skill loss)
     */
    private function adjustAbilityForAge(int $rawAbility, int $marketValueCents, int $age): int
    {
        // YOUNG PLAYERS (under 23): Cap ability based on age
        // A €120M 17-year-old is valued for potential, not peak skill
        if ($age < 23) {
            // Base cap increases with age: 17yo = 75, 22yo = 85
            $ageCap = 73 + ($age - 17) * 2;

            // Exceptional market value raises the cap (they're playing at top level)
            if ($marketValueCents >= 10_000_000_000) { // €100M+
                $ageCap += 8;  // Can reach 91 at 22
            } elseif ($marketValueCents >= 5_000_000_000) { // €50M+
                $ageCap += 5;  // Can reach 88 at 22
            } elseif ($marketValueCents >= 2_000_000_000) { // €20M+
                $ageCap += 3;  // Can reach 86 at 22
            }

            return min($rawAbility, $ageCap);
        }

        // PRIME YEARS (23-30): Use raw ability directly
        if ($age <= 30) {
            return $rawAbility;
        }

        // VETERANS (31+): Boost ability based on how exceptional their value is for age
        // The fact that they still command significant value means they're still good

        // Typical market value for a player of this age
        $typicalValueForAge = match (true) {
            $age <= 32 => 500_000_000,   // €5M
            $age <= 34 => 300_000_000,   // €3M
            $age <= 36 => 150_000_000,   // €1.5M
            default => 80_000_000,        // €800K
        };

        // How much above typical is this player?
        $valueRatio = $marketValueCents / max(1, $typicalValueForAge);

        // Boost ability for exceptional veterans
        // A 36yo worth €15M when typical is €1.5M has ratio of 10 = elite
        $abilityBoost = match (true) {
            $valueRatio >= 10 => 12,  // 10x+ typical = still world class
            $valueRatio >= 5 => 8,    // 5-10x typical = very good veteran
            $valueRatio >= 3 => 5,    // 3-5x typical = above average
            $valueRatio >= 2 => 3,    // 2-3x typical = solid
            $valueRatio >= 1 => 1,    // At typical = average for age
            default => 0,             // Below typical
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
