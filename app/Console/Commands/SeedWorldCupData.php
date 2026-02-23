<?php

namespace App\Console\Commands;

use App\Modules\Squad\Services\PlayerValuationService;
use App\Support\CountryCodeMapper;
use App\Support\TeamColors;
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

    protected $description = 'Seed World Cup national teams and players into the main teams/players tables';

    private const COMPETITION_ID = 'WC2026';
    private const SEASON = '2025';

    /** @var array<int, array{team_name: string, fifa_code: string, group_letter: string, is_placeholder: bool}> */
    private array $csvTeams = [];

    /** @var array<int, array{match_number: int, home_team_id: int, away_team_id: int, stage_id: int, kickoff_at: string, match_label: string}> */
    private array $csvMatches = [];

    /** @var array<int, string> csv_id → fifa_code */
    private array $csvIdToFifaCode = [];

    /** @var array<string, string> team_name from JSON → transfermarkt_id (filename) */
    private array $jsonTeamNames = [];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        if (!$this->parseCSVs()) {
            return CommandAlias::FAILURE;
        }
        $this->loadJsonTeamNames();
        $this->seedCompetition();
        $teamMapping = $this->seedTeams();
        $playerCount = $this->seedPlayers();

        $this->generateTeamMapping($teamMapping);
        $this->generateGroupsJson($teamMapping);
        $this->generateScheduleJson();
        $this->generateBracketJson();
        $this->displaySummary($teamMapping, $playerCount);

        return CommandAlias::SUCCESS;
    }

    private function clearExistingData(): void
    {
        $this->info('Clearing existing World Cup data...');

        $teamIds = DB::table('teams')->where('type', 'national')->pluck('id');

        if ($teamIds->isNotEmpty()) {
            // Delete from tables that reference both teams and competition
            DB::table('game_player_templates')->whereIn('team_id', $teamIds)->delete();
            DB::table('match_events')->whereIn('team_id', $teamIds)->delete();
            DB::table('game_players')->whereIn('team_id', $teamIds)->delete();
            DB::table('game_standings')->whereIn('team_id', $teamIds)->delete();
            DB::table('game_matches')
                ->whereIn('home_team_id', $teamIds)
                ->orWhereIn('away_team_id', $teamIds)
                ->delete();
            DB::table('cup_ties')
                ->whereIn('home_team_id', $teamIds)
                ->orWhereIn('away_team_id', $teamIds)
                ->delete();
            DB::table('competition_entries')->whereIn('team_id', $teamIds)->delete();
            DB::table('competition_teams')->whereIn('team_id', $teamIds)->delete();
        }

        // Delete remaining rows that reference the competition by competition_id
        $wcMatchIds = DB::table('game_matches')
            ->where('competition_id', self::COMPETITION_ID)
            ->pluck('id');
        if ($wcMatchIds->isNotEmpty()) {
            DB::table('match_events')->whereIn('game_match_id', $wcMatchIds)->delete();
        }
        DB::table('game_matches')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('game_standings')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('cup_ties')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('competition_entries')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('competition_teams')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('simulated_seasons')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('games')->where('competition_id', self::COMPETITION_ID)->delete();

        if ($teamIds->isNotEmpty()) {
            DB::table('teams')->where('type', 'national')->delete();
        }

        DB::table('competitions')->where('id', self::COMPETITION_ID)->delete();

        $this->info('Cleared.');
    }

    private function parseCSVs(): bool
    {
        $this->info('Parsing CSV files...');

        // Parse teams.csv
        $teamsPath = base_path('data/2025/WC2026/raw/teams.csv');
        if (!file_exists($teamsPath)) {
            $this->error("Teams CSV not found: {$teamsPath}");
            return false;
        }
        $handle = fopen($teamsPath, 'r');
        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $csvId = (int) $row[0];
            $this->csvTeams[$csvId] = [
                'team_name' => $row[1],
                'fifa_code' => $row[2],
                'group_letter' => $row[3],
                'is_placeholder' => strtolower($row[4]) === 'true',
            ];
            $this->csvIdToFifaCode[$csvId] = $row[2];
        }
        fclose($handle);

        // Parse matches.csv
        $matchesPath = base_path('data/2025/WC2026/raw/matches.csv');
        if (!file_exists($matchesPath)) {
            $this->error("Matches CSV not found: {$matchesPath}");
            return false;
        }
        $handle = fopen($matchesPath, 'r');
        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $this->csvMatches[] = [
                'match_number' => (int) $row[1],
                'home_team_id' => (int) $row[2] ?: null,
                'away_team_id' => (int) $row[3] ?: null,
                'stage_id' => (int) $row[5],
                'kickoff_at' => $row[6],
                'match_label' => $row[7],
            ];
        }
        fclose($handle);

        $this->line("  Teams CSV: " . count($this->csvTeams) . " entries");
        $this->line("  Matches CSV: " . count($this->csvMatches) . " entries");

        return true;
    }

    private function loadJsonTeamNames(): void
    {
        $basePath = base_path('data/2025/WC2026/teams');
        if (!is_dir($basePath)) {
            return;
        }

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = json_decode(file_get_contents($filePath), true);
            if (!$data || empty($data['name'])) {
                continue;
            }
            $tmId = pathinfo($filePath, PATHINFO_FILENAME);
            $this->jsonTeamNames[$data['name']] = $tmId;
        }
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
     * Seed all 48 teams from CSV.
     *
     * @return array<string, array{uuid: string, csv_id: int, name: string, group: string, is_placeholder: bool, transfermarkt_id: string|null}>
     */
    private function seedTeams(): array
    {
        $this->info('Seeding teams...');
        $teamMapping = [];

        foreach ($this->csvTeams as $csvId => $team) {
            $countryCode = CountryCodeMapper::toCode($team['team_name']);
            $transfermarktId = $this->jsonTeamNames[$team['team_name']] ?? null;

            // Check for existing team by country code (for non-placeholders)
            $existing = null;
            if ($countryCode && !$team['is_placeholder']) {
                $existing = DB::table('teams')
                    ->where('type', 'national')
                    ->where('country', $countryCode)
                    ->first();
            }

            if ($existing) {
                $teamId = $existing->id;
                $updateData = [];
                if ($transfermarktId && !$existing->transfermarkt_id) {
                    $updateData['transfermarkt_id'] = $transfermarktId;
                }
                if (!$existing->colors) {
                    $updateData['colors'] = json_encode(TeamColors::get($team['team_name']));
                }
                if (!empty($updateData)) {
                    DB::table('teams')->where('id', $teamId)->update($updateData);
                }
            } else {
                $teamId = Str::uuid()->toString();
                DB::table('teams')->insert([
                    'id' => $teamId,
                    'transfermarkt_id' => $transfermarktId,
                    'type' => 'national',
                    'name' => $team['team_name'],
                    'country' => $countryCode ?? 'TBD',
                    'image' => null,
                    'stadium_name' => null,
                    'stadium_seats' => 0,
                    'colors' => json_encode(TeamColors::get($team['team_name'])),
                ]);
            }

            // Link to competition
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => self::COMPETITION_ID,
                    'team_id' => $teamId,
                    'season' => self::SEASON,
                ],
                []
            );

            $teamMapping[$team['fifa_code']] = [
                'uuid' => $teamId,
                'csv_id' => $csvId,
                'name' => $team['team_name'],
                'group' => $team['group_letter'],
                'is_placeholder' => $team['is_placeholder'],
                'transfermarkt_id' => $transfermarktId,
            ];
        }

        $this->line("  Teams seeded: " . count($teamMapping));

        return $teamMapping;
    }

    /**
     * Seed players for teams that have JSON roster files.
     */
    private function seedPlayers(): int
    {
        $this->info('Seeding players...');
        $basePath = base_path('data/2025/WC2026/teams');

        if (!is_dir($basePath)) {
            $this->warn('No teams directory found — skipping player seeding.');
            return 0;
        }

        $valuationService = app(PlayerValuationService::class);
        $playerCount = 0;

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = json_decode(file_get_contents($filePath), true);
            if (!$data || empty($data['players'])) {
                continue;
            }

            foreach ($data['players'] as $player) {
                $transfermarktId = $player['id'] ?? null;
                if (!$transfermarktId) {
                    continue;
                }

                if (DB::table('players')->where('transfermarkt_id', $transfermarktId)->exists()) {
                    $playerCount++;
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

                $marketValueCents = Money::parseMarketValue($player['marketValue'] ?? null);
                $position = $player['position'] ?? 'Central Midfield';
                [$technical, $physical] = $valuationService->marketValueToAbilities($marketValueCents, $position, $age ?? 25);

                DB::table('players')->insert([
                    'id' => Str::uuid()->toString(),
                    'transfermarkt_id' => $transfermarktId,
                    'name' => $player['name'],
                    'date_of_birth' => $dateOfBirth,
                    'nationality' => json_encode($player['nationality'] ?? []),
                    'height' => $player['height'] ?? null,
                    'foot' => $foot,
                    'technical_ability' => $technical,
                    'physical_ability' => $physical,
                ]);

                $playerCount++;
            }
        }

        $this->line("  Players seeded: {$playerCount}");

        return $playerCount;
    }

    /**
     * Generate team_mapping.json (FIFA code → UUID bridge).
     */
    private function generateTeamMapping(array $teamMapping): void
    {
        $path = base_path('data/2025/WC2026/team_mapping.json');
        file_put_contents($path, json_encode($teamMapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Generated: data/2025/WC2026/team_mapping.json (" . count($teamMapping) . " teams)");
    }

    /**
     * Generate groups.json from CSV data with FIFA codes as team keys.
     */
    private function generateGroupsJson(array $teamMapping): void
    {
        // Build groups from CSV team data
        $groups = [];
        foreach ($this->csvTeams as $team) {
            $group = $team['group_letter'];
            if (!isset($groups[$group])) {
                $groups[$group] = ['teams' => [], 'matches' => []];
            }
            $groups[$group]['teams'][] = $team['fifa_code'];
        }

        // Group stage matches (stage_id = 1)
        $groupStageMatches = array_filter($this->csvMatches, fn ($m) => $m['stage_id'] === 1);

        // Group matches by group label and determine round numbers
        $matchesByGroup = [];
        foreach ($groupStageMatches as $match) {
            $groupLabel = str_replace('Group ', '', $match['match_label']);
            $matchesByGroup[$groupLabel][] = $match;
        }

        foreach ($matchesByGroup as $groupLabel => $matches) {
            // Sort by match_number to maintain order
            usort($matches, fn ($a, $b) => $a['match_number'] <=> $b['match_number']);

            // Derive round numbers: matches 1-2 = round 1, matches 3-4 = round 2, matches 5-6 = round 3
            $matchIndex = 0;
            foreach ($matches as $match) {
                $round = intdiv($matchIndex, 2) + 1;
                $homeCode = $this->csvIdToFifaCode[$match['home_team_id']] ?? null;
                $awayCode = $this->csvIdToFifaCode[$match['away_team_id']] ?? null;

                if (!$homeCode || !$awayCode) {
                    continue;
                }

                $date = Carbon::parse($match['kickoff_at'])->toDateString();

                $groups[$groupLabel]['matches'][] = [
                    'round' => $round,
                    'home' => $homeCode,
                    'away' => $awayCode,
                    'date' => $date,
                ];

                $matchIndex++;
            }
        }

        // Sort groups by label (A, B, C, ... L)
        ksort($groups);

        $path = base_path('data/2025/WC2026/groups.json');
        file_put_contents($path, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $totalMatches = array_sum(array_map(fn ($g) => count($g['matches']), $groups));
        $this->info("Generated: data/2025/WC2026/groups.json (" . count($groups) . " groups, {$totalMatches} matches)");
    }

    /**
     * Generate schedule.json with knockout round config.
     */
    private function generateScheduleJson(): void
    {
        $stageMap = [
            2 => ['round' => 1, 'name' => 'Dieciseisavos de final'],
            3 => ['round' => 2, 'name' => 'Octavos de final'],
            4 => ['round' => 3, 'name' => 'Cuartos de final'],
            5 => ['round' => 4, 'name' => 'Semifinal'],
            6 => ['round' => 5, 'name' => 'Tercer puesto'],
            7 => ['round' => 6, 'name' => 'Final'],
        ];

        // Find earliest date per stage_id
        $knockout = [];
        foreach ($stageMap as $stageId => $roundInfo) {
            $stageMatches = array_filter($this->csvMatches, fn ($m) => $m['stage_id'] === $stageId);
            if (empty($stageMatches)) {
                continue;
            }

            $dates = array_map(fn ($m) => Carbon::parse($m['kickoff_at'])->toDateString(), $stageMatches);
            sort($dates);

            $knockout[] = [
                'round' => $roundInfo['round'],
                'name' => $roundInfo['name'],
                'date' => $dates[0],
            ];
        }

        $schedule = ['knockout' => $knockout];
        $path = base_path('data/2025/WC2026/schedule.json');
        file_put_contents($path, json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Generated: data/2025/WC2026/schedule.json (" . count($knockout) . " rounds)");
    }

    /**
     * Generate bracket.json with fixed knockout bracket structure.
     */
    private function generateBracketJson(): void
    {
        $roundMap = [
            2 => 'round_of_32',
            3 => 'round_of_16',
            4 => 'quarter_finals',
            5 => 'semi_finals',
            6 => 'third_place',
            7 => 'final',
        ];

        $bracket = [];
        foreach ($roundMap as $stageId => $roundKey) {
            $bracket[$roundKey] = [];
        }

        $knockoutMatches = array_filter($this->csvMatches, fn ($m) => $m['stage_id'] >= 2);

        foreach ($knockoutMatches as $match) {
            $roundKey = $roundMap[$match['stage_id']] ?? null;
            if (!$roundKey) {
                continue;
            }

            // Parse match_label: "2A vs 2B", "W73 vs W75", etc.
            $label = $match['match_label'];
            $parts = explode(' vs ', $label);

            $home = $parts[0] ?? $label;
            $away = $parts[1] ?? '';

            $bracket[$roundKey][] = [
                'match_number' => $match['match_number'],
                'home' => trim($home),
                'away' => trim($away),
                'date' => Carbon::parse($match['kickoff_at'])->toDateString(),
            ];
        }

        // Sort each round by match_number
        foreach ($bracket as &$matches) {
            usort($matches, fn ($a, $b) => $a['match_number'] <=> $b['match_number']);
        }
        unset($matches);

        $path = base_path('data/2025/WC2026/bracket.json');
        file_put_contents($path, json_encode($bracket, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $totalKnockout = array_sum(array_map(fn ($r) => count($r), $bracket));
        $this->info("Generated: data/2025/WC2026/bracket.json ({$totalKnockout} knockout matches)");
    }

    private function displaySummary(array $teamMapping, int $playerCount): void
    {
        $realTeams = count(array_filter($teamMapping, fn ($t) => !$t['is_placeholder']));
        $placeholders = count(array_filter($teamMapping, fn ($t) => $t['is_placeholder']));
        $withRosters = count(array_filter($teamMapping, fn ($t) => $t['transfermarkt_id'] !== null));

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Total Teams: " . count($teamMapping) . " ({$realTeams} real, {$placeholders} placeholders)");
        $this->line("  Teams with rosters: {$withRosters}");
        $this->line("  Total Players: {$playerCount}");
        $this->line("  Groups: " . count(array_unique(array_column($teamMapping, 'group'))));
        $this->newLine();
        $this->info('World Cup data seeded successfully!');
    }
}
