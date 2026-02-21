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

    protected $description = 'Seed World Cup national teams and players into the main teams/players tables';

    private const COMPETITION_ID = 'WC2026';
    private const SEASON = '2025';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        $this->seedCompetition();
        $this->seedTeamsAndPlayers();
        $this->displaySummary();

        return CommandAlias::SUCCESS;
    }

    private function clearExistingData(): void
    {
        $this->info('Clearing existing World Cup data...');

        // Remove competition_teams links for WC2026
        DB::table('competition_teams')
            ->where('competition_id', self::COMPETITION_ID)
            ->delete();

        // Remove national teams (don't delete Players — they may be shared with career mode)
        DB::table('teams')->where('type', 'national')->delete();

        // Remove the competition record
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

    private function seedTeamsAndPlayers(): void
    {
        $basePath = base_path('data/2025/WC/teams');

        if (!is_dir($basePath)) {
            $this->error('World Cup teams directory not found: data/2025/WC/teams/');
            return;
        }

        $valuationService = app(PlayerValuationService::class);
        $teamCount = 0;
        $playerCount = 0;

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = json_decode(file_get_contents($filePath), true);
            if (!$data) {
                continue;
            }

            // Extract team key from filename (e.g., "3375" from "3375.json")
            $teamKey = pathinfo($filePath, PATHINFO_FILENAME);
            $countryCode = CountryCodeMapper::toCode($data['name']) ?? $teamKey;

            // Create or find the Team record
            $teamId = $this->seedTeam($teamKey, $data, $countryCode);
            if (!$teamId) {
                continue;
            }

            $teamCount++;

            // Seed players
            $players = $data['players'] ?? [];
            $playerCount += $this->seedPlayers($teamId, $players, $valuationService);
        }

        $this->line("  Teams: {$teamCount}");
        $this->line("  Players: {$playerCount}");
    }

    private function seedTeam(string $teamKey, array $data, string $countryCode): ?string
    {
        // Use the team key as a pseudo transfermarkt_id for national teams
        $existing = DB::table('teams')
            ->where('type', 'national')
            ->where('country', $countryCode)
            ->first();

        if ($existing) {
            $teamId = $existing->id;
        } else {
            $teamId = Str::uuid()->toString();

            DB::table('teams')->insert([
                'id' => $teamId,
                'transfermarkt_id' => $teamKey,
                'type' => 'national',
                'name' => $data['name'],
                'country' => $countryCode,
                'image' => null,
                'stadium_name' => null,
                'stadium_seats' => 0,
            ]);
        }

        // Link team to WC2026 competition
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

    private function seedPlayers(string $teamId, array $players, PlayerValuationService $valuationService): int
    {
        $count = 0;

        foreach ($players as $player) {
            $transfermarktId = $player['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            // Skip if player already exists (may be shared with career mode)
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

            $count++;
        }

        return $count;
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('Summary:');
        $this->line('  National Teams: ' . DB::table('teams')->where('type', 'national')->count());
        $this->line('  WC Competition Teams: ' . DB::table('competition_teams')->where('competition_id', self::COMPETITION_ID)->count());
        $this->line('  Total Players: ' . DB::table('players')->count());
        $this->newLine();
        $this->info('World Cup data seeded successfully!');
    }
}
