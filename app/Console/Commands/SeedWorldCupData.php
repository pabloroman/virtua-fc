<?php

namespace App\Console\Commands;

use App\Game\Services\PlayerValuationService;
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

    protected $description = 'Seed World Cup national teams and players from JSON data (isolated from career mode)';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        $this->seedWorldCupTeams();
        $this->displaySummary();

        return CommandAlias::SUCCESS;
    }

    private function clearExistingData(): void
    {
        $this->info('Clearing existing World Cup data...');

        DB::table('wc_players')->delete();
        DB::table('wc_teams')->delete();

        $this->info('Cleared.');
    }

    private function seedWorldCupTeams(): void
    {
        $filePath = base_path('data/2025/WC/teams.json');

        if (!file_exists($filePath)) {
            $this->error('World Cup data file not found: data/2025/WC/teams.json');
            return;
        }

        $data = json_decode(file_get_contents($filePath), true);
        $teams = $data['teams'] ?? [];

        if (empty($teams)) {
            $this->warn('No teams found in World Cup data file.');
            return;
        }

        $this->newLine();
        $this->info("=== {$data['name']} ===");

        $valuationService = app(PlayerValuationService::class);
        $teamCount = 0;
        $playerCount = 0;

        foreach ($teams as $team) {
            $teamId = $this->seedWcTeam($team);

            if (!$teamId) {
                continue;
            }

            $teamCount++;

            $players = $team['players'] ?? [];
            $playerCount += $this->seedWcPlayers($teamId, $players, $valuationService);
        }

        $this->line("  Teams: {$teamCount}");
        $this->line("  Players: {$playerCount}");
    }

    private function seedWcTeam(array $team): ?string
    {
        $countryCode = $team['countryCode'] ?? null;

        if (!$countryCode) {
            $this->warn("  Skipping team without countryCode: {$team['name']}");
            return null;
        }

        $existing = DB::table('wc_teams')
            ->where('country_code', $countryCode)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $teamId = Str::uuid()->toString();

        DB::table('wc_teams')->insert([
            'id' => $teamId,
            'name' => $team['name'],
            'short_name' => $team['shortName'] ?? strtoupper(substr($team['name'], 0, 3)),
            'country_code' => $countryCode,
            'confederation' => $team['confederation'] ?? 'UEFA',
            'image' => $team['image'] ?? null,
            'strength' => $team['strength'] ?? 50,
            'pot' => $team['pot'] ?? 4,
        ]);

        return $teamId;
    }

    private function seedWcPlayers(string $teamId, array $players, PlayerValuationService $valuationService): int
    {
        $count = 0;
        $rows = [];

        foreach ($players as $player) {
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

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'wc_team_id' => $teamId,
                'name' => $player['name'],
                'date_of_birth' => $dateOfBirth,
                'nationality' => json_encode($player['nationality'] ?? []),
                'height' => $player['height'] ?? null,
                'foot' => $foot,
                'position' => $position,
                'number' => $player['number'] ?? null,
                'technical_ability' => $technical,
                'physical_ability' => $physical,
            ];

            $count++;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('wc_players')->insert($chunk);
        }

        return $count;
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->line('  WC Teams: ' . DB::table('wc_teams')->count());
        $this->line('  WC Players: ' . DB::table('wc_players')->count());
        $this->newLine();
        $this->info('World Cup data seeded successfully!');
    }
}
