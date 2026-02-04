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

    protected $description = 'Seed teams, competitions, fixtures, and players from 2025 season JSON data files';

    private array $competitionsToSeed = [
        [
            'code' => 'ESP1',
            'path' => 'data/2025/ESP1',
            'tier' => 1,
            'handler' => 'league',
            'minimum_annual_wage' => 20_000_000, // €200,000 in cents (La Liga minimum)
        ],
        [
            'code' => 'ESP2',
            'path' => 'data/2025/ESP2',
            'tier' => 2,
            'handler' => 'league_with_playoff',
            'minimum_annual_wage' => 10_000_000, // €100,000 in cents (La Liga 2 minimum)
        ],
        [
            'code' => 'ESPSUP',
            'path' => 'data/2025/ESPSUP',
            'tier' => 0,
            'handler' => 'knockout_cup',
            'minimum_annual_wage' => null,
        ],
        [
            'code' => 'ESPCUP',
            'path' => 'data/2025/ESPCUP',
            'tier' => 0,
            'handler' => 'knockout_cup',
            'minimum_annual_wage' => null,
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
        $minimumAnnualWage = $config['minimum_annual_wage'] ?? null;

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
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $fixturesData = $this->loadJson("{$basePath}/fixtures.json");

        // Seed competition record
        $this->seedCompetitionRecord($code, $teamsData, $tier, 'league', $handler, $minimumAnnualWage);

        // Build team ID mapping (transfermarktId -> UUID)
        $teamIdMap = $this->seedTeams($teamsData['clubs'], $code, $teamsData['seasonID']);

        // Seed players (embedded in teams data)
        $this->seedPlayersFromTeams($teamsData['clubs'], $teamIdMap);

        // Seed fixtures
        $this->seedFixtures($fixturesData['matchdays'], $code, $teamsData['seasonID'], $teamIdMap);
    }

    private function seedCupCompetition(string $basePath, string $code, int $tier, string $handler, ?int $minimumAnnualWage): void
    {
        $teamsData = $this->loadJson("{$basePath}/teams.json");
        $roundsData = $this->loadJson("{$basePath}/rounds.json");
        $matchdaysData = $this->loadJson("{$basePath}/matchdays.json");

        $season = '2025';

        // Seed competition record
        $this->seedCompetitionRecord($code, $teamsData, $tier, 'cup', $handler, $minimumAnnualWage);

        // Seed cup round templates
        $this->seedCupRoundTemplates($code, $season, $roundsData, $matchdaysData);

        // Seed cup teams (link existing teams to cup)
        $this->seedCupTeams($teamsData['clubs'], $code, $season);
    }

    private function seedCompetitionRecord(string $code, array $data, int $tier, string $type, string $handler, ?int $minimumAnnualWage): void
    {
        $season = $data['seasonID'] ?? '2025';

        DB::table('competitions')->updateOrInsert(
            ['id' => $code],
            [
                'name' => $data['name'],
                'country' => 'ES',
                'tier' => $tier,
                'type' => $type,
                'handler_type' => $handler,
                'season' => $season,
                'minimum_annual_wage' => $minimumAnnualWage,
            ]
        );

        $wageDisplay = $minimumAnnualWage
            ? '€' . number_format($minimumAnnualWage / 100, 0, ',', '.') . '/year min'
            : 'no minimum (cup)';
        $this->line("  Competition: {$data['name']} ({$wageDisplay})");
    }

    /**
     * Seed teams and return mapping of transfermarktId -> UUID.
     */
    private function seedTeams(array $clubs, string $competitionId, string $season): array
    {
        $teamIdMap = [];
        $count = 0;

        foreach ($clubs as $club) {
            $transfermarktId = $club['transfermarktId'] ?? null;
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
                    'country' => 'ES',
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
            $transfermarktId = $club['transfermarktId'] ?? null;
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

    private function seedCupTeams(array $clubs, string $competitionId, string $season): void
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
                    'country' => 'ES',
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
