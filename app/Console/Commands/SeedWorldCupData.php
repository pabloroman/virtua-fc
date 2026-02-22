<?php

namespace App\Console\Commands;

use App\Modules\Squad\Services\PlayerValuationService;
use App\Support\CountryCodeMapper;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SeedWorldCupData extends Command
{
    protected $signature = 'app:seed-world-cup-data
                            {--fresh : Clear existing World Cup data before seeding}';

    protected $description = 'Seed World Cup national teams, players, and group stage schedule';

    private const COMPETITION_ID = 'WC2026';
    private const SEASON = '2025';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        $this->seedCompetition();
        $teamKeyMap = $this->seedTeamsAndPlayers();
        $this->seedGroupAssignments($teamKeyMap);
        $this->displaySummary();

        return CommandAlias::SUCCESS;
    }

    private function clearExistingData(): void
    {
        $this->info('Clearing existing World Cup data...');

        DB::table('competition_teams')
            ->where('competition_id', self::COMPETITION_ID)
            ->delete();

        DB::table('teams')->where('type', 'national')->delete();

        DB::table('competitions')->where('id', self::COMPETITION_ID)->delete();

        $this->info('Cleared.');
    }

    private function seedCompetition(): void
    {
        DB::table('competitions')->updateOrInsert(
            ['id' => self::COMPETITION_ID],
            [
                'name' => 'Copa del Mundo FIFA 2026',
                'country' => 'INT',
                'tier' => 0,
                'type' => 'league',
                'role' => 'league',
                'scope' => 'continental',
                'handler_type' => 'group_stage_cup',
                'season' => self::SEASON,
            ]
        );

        $this->info('Competition: Copa del Mundo FIFA 2026');
    }

    /**
     * Seed national teams and their players from individual team JSON files.
     * Returns a map of transfermarkt_id => team UUID for group assignment.
     */
    private function seedTeamsAndPlayers(): array
    {
        $basePath = base_path('data/2025/WC/teams');

        if (!is_dir($basePath)) {
            $this->error('World Cup teams directory not found: data/2025/WC/teams/');
            return [];
        }

        $valuationService = app(PlayerValuationService::class);
        $teamKeyMap = [];
        $teamCount = 0;
        $playerCount = 0;

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = json_decode(file_get_contents($filePath), true);
            if (!$data) {
                continue;
            }

            $teamKey = $data['transfermarktId'] ?? pathinfo($filePath, PATHINFO_FILENAME);
            $teamName = $data['name'];
            $countryCode = CountryCodeMapper::toCode($teamName) ?? $teamKey;

            $teamId = $this->seedTeam($teamKey, $data, $countryCode);
            if (!$teamId) {
                continue;
            }

            $teamKeyMap[$teamKey] = $teamId;
            $teamCount++;

            $players = $data['players'] ?? [];
            $playerCount += $this->seedPlayers($teamId, $teamName, $players, $valuationService);
        }

        $this->line("  Teams: {$teamCount}");
        $this->line("  Players: {$playerCount}");

        return $teamKeyMap;
    }

    private function seedTeam(string $teamKey, array $data, string $countryCode): ?string
    {
        $existing = DB::table('teams')
            ->where('transfermarkt_id', $teamKey)
            ->where('type', 'national')
            ->first();

        if ($existing) {
            $teamId = $existing->id;
        } else {
            $teamId = Str::uuid()->toString();

            $stadiumSeats = isset($data['stadiumSeats'])
                ? (int) str_replace(['.', ','], '', $data['stadiumSeats'])
                : 0;

            DB::table('teams')->insert([
                'id' => $teamId,
                'transfermarkt_id' => $teamKey,
                'type' => 'national',
                'name' => $data['name'],
                'country' => $countryCode,
                'image' => $data['image'] ?? null,
                'stadium_name' => $data['stadiumName'] ?? null,
                'stadium_seats' => $stadiumSeats,
            ]);
        }

        // Link team to WC2026 competition (group_label set later by seedGroupAssignments)
        DB::table('competition_teams')->updateOrInsert(
            [
                'competition_id' => self::COMPETITION_ID,
                'team_id' => $teamId,
                'season' => self::SEASON,
            ],
            []
        );

        return $teamId;
    }

    private function seedPlayers(string $teamId, string $teamName, array $players, PlayerValuationService $valuationService): int
    {
        $count = 0;

        foreach ($players as $player) {
            $transfermarktId = $player['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            $exists = DB::table('players')
                ->where('transfermarkt_id', $transfermarktId)
                ->exists();

            if ($exists) {
                $count++;
                continue;
            }

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

            $foot = match (strtolower($player['foot'] ?? '')) {
                'left' => 'left',
                'right' => 'right',
                'both' => 'both',
                default => null,
            };

            // Guess nationality from team name when not provided in player data
            $nationality = $player['nationality'] ?? [$teamName];

            $marketValueCents = Money::parseMarketValue($player['marketValue'] ?? null);
            $position = $player['position'] ?? 'Central Midfield';
            [$technical, $physical] = $valuationService->marketValueToAbilities($marketValueCents, $position, $age ?? 25);

            DB::table('players')->insert([
                'id' => Str::uuid()->toString(),
                'transfermarkt_id' => $transfermarktId,
                'name' => $player['name'],
                'date_of_birth' => $dateOfBirth,
                'nationality' => json_encode($nationality),
                'height' => $player['height'] ?? null,
                'foot' => $foot,
                'technical_ability' => $technical,
                'physical_ability' => $physical,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Read groups.json and schedule.json to store group assignments on competition_teams
     * and validate that all team references are resolvable.
     */
    private function seedGroupAssignments(array $teamKeyMap): void
    {
        $groupsPath = base_path('data/2025/WC/groups.json');
        if (!file_exists($groupsPath)) {
            $this->warn('groups.json not found — skipping group assignments.');
            return;
        }

        $groupsData = json_decode(file_get_contents($groupsPath), true);
        if (!$groupsData) {
            $this->error('Failed to parse groups.json.');
            return;
        }

        $this->newLine();
        $this->info('Group stage schedule:');

        $matchCount = 0;
        $unresolvedTeams = [];

        foreach ($groupsData as $groupLabel => $groupInfo) {
            $teamNames = [];

            // Assign group_label to each team in competition_teams
            foreach ($groupInfo['teams'] as $teamKey) {
                $teamId = $teamKeyMap[$teamKey] ?? null;

                if (!$teamId) {
                    $unresolvedTeams[] = $teamKey;
                    continue;
                }

                DB::table('competition_teams')
                    ->where('competition_id', self::COMPETITION_ID)
                    ->where('team_id', $teamId)
                    ->where('season', self::SEASON)
                    ->update(['group_label' => $groupLabel]);

                $teamName = DB::table('teams')->where('id', $teamId)->value('name');
                $teamNames[$teamKey] = $teamName;
            }

            // Display group
            $this->line("  Group {$groupLabel}: " . implode(', ', $teamNames));

            // Validate and display match pairings
            foreach ($groupInfo['matches'] as $match) {
                $homeName = $teamNames[$match['home']] ?? "? ({$match['home']})";
                $awayName = $teamNames[$match['away']] ?? "? ({$match['away']})";
                $this->line("    R{$match['round']} {$match['date']}: {$homeName} vs {$awayName}");
                $matchCount++;
            }
        }

        // Display knockout schedule from schedule.json
        $schedulePath = base_path('data/2025/WC/schedule.json');
        if (file_exists($schedulePath)) {
            $scheduleData = json_decode(file_get_contents($schedulePath), true);
            $knockoutRounds = $scheduleData['knockout'] ?? [];

            if ($knockoutRounds) {
                $this->newLine();
                $this->info('Knockout schedule:');
                foreach ($knockoutRounds as $round) {
                    $this->line("  R{$round['round']} {$round['date']}: {$round['name']}");
                }
            }
        }

        if ($unresolvedTeams) {
            $this->newLine();
            $this->warn('Unresolved team references: ' . implode(', ', array_unique($unresolvedTeams)));
        }

        $this->newLine();
        $this->line("  Group stage matches: {$matchCount}");
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->line('  National Teams: ' . DB::table('teams')->where('type', 'national')->count());
        $this->line('  WC Competition Teams: ' . DB::table('competition_teams')->where('competition_id', self::COMPETITION_ID)->count());

        $groupedTeams = DB::table('competition_teams')
            ->where('competition_id', self::COMPETITION_ID)
            ->whereNotNull('group_label')
            ->count();
        $this->line("  Teams with group assignment: {$groupedTeams}");

        $this->line('  Total Players: ' . DB::table('players')->count());
        $this->newLine();
        $this->info('World Cup data seeded successfully!');
    }
}
