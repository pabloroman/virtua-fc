<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Player\Services\PlayerValuationService;
use App\Modules\Season\Services\GamePlayerTemplateService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedConferenceLeague extends Command
{
    protected $signature = 'app:seed-conference-league';

    protected $description = 'Seed the UECL competition record, import missing EUR teams, and link teams (one-off)';

    public function handle(): int
    {
        $existing = DB::table('competitions')->where('id', 'UECL')->first();

        if ($existing) {
            $this->warn('UECL competition already exists. Skipping.');

            return self::SUCCESS;
        }

        $teamsData = json_decode(file_get_contents(base_path('data/2025/UECL/teams.json')), true);
        $season = $teamsData['seasonID'] ?? '2025';

        // Build team lookup once — importMissingEurTeams adds new entries to it
        $teamsByTransfermarktId = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        // Step 1: Import any missing EUR pool teams
        $this->importMissingEurTeams($teamsData['clubs'], $season, $teamsByTransfermarktId);

        // Step 2: Create competition record
        DB::table('competitions')->insert([
            'id' => 'UECL',
            'name' => $teamsData['name'],
            'country' => 'EU',
            'flag' => 'eu',
            'tier' => 0,
            'type' => 'league',
            'role' => 'european',
            'scope' => 'continental',
            'handler_type' => 'swiss_format',
            'season' => $season,
        ]);

        $this->info("Competition created: {$teamsData['name']}");

        $count = 0;

        foreach ($teamsData['clubs'] as $club) {
            $transfermarktId = $club['id'] ?? null;

            if (! $transfermarktId) {
                continue;
            }

            $teamId = $teamsByTransfermarktId[$transfermarktId] ?? null;

            if (! $teamId) {
                $this->warn("  Team not found for transfermarkt_id {$transfermarktId}: {$club['name']}");

                continue;
            }

            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => 'UECL',
                    'team_id' => $teamId,
                    'season' => $season,
                ],
                [
                    'entry_round' => 1,
                ]
            );

            $count++;
        }

        $this->info("Teams linked: {$count}");

        // Step 4: Regenerate player templates so new UECL teams are included
        $this->info('Regenerating player templates...');
        $templateService = app(GamePlayerTemplateService::class);
        $countryConfig = app(CountryConfig::class);

        foreach ($countryConfig->playableCountryCodes() as $countryCode) {
            $templateService->clearTemplatesForCountry($season, $countryCode);
            $generated = $templateService->generateTemplates($season, $countryCode);
            $this->line("  {$countryCode}: {$generated} player templates");
        }

        return self::SUCCESS;
    }

    /**
     * Import EUR pool teams that don't exist yet in the database.
     * Creates team records and seeds their players from the EUR JSON files.
     */
    private function importMissingEurTeams(array $clubs, string $season, array &$teamsByTransfermarktId): void
    {
        $eurPath = base_path('data/2025/EUR');
        $valuationService = app(PlayerValuationService::class);
        $imported = 0;

        foreach ($clubs as $club) {
            $transfermarktId = $club['id'] ?? null;

            if (! $transfermarktId || isset($teamsByTransfermarktId[$transfermarktId])) {
                continue;
            }

            // Check if a EUR JSON file exists for this team
            $filePath = "{$eurPath}/{$transfermarktId}.json";

            if (! file_exists($filePath)) {
                $this->warn("  No EUR data file for transfermarkt_id {$transfermarktId}: {$club['name']}");

                continue;
            }

            $data = json_decode(file_get_contents($filePath), true);
            $teamCountry = $data['country'] ?? $club['country'] ?? 'EU';

            // Create team record
            $teamId = Str::uuid()->toString();
            DB::table('teams')->insert([
                'id' => $teamId,
                'transfermarkt_id' => $transfermarktId,
                'name' => $data['name'] ?? $club['name'],
                'slug' => Str::slug($data['name'] ?? $club['name']),
                'country' => $teamCountry,
                'image' => $data['image'] ?? null,
                'stadium_name' => $data['stadiumName'] ?? null,
                'stadium_seats' => isset($data['stadiumSeats'])
                    ? (int) str_replace(['.', ','], '', $data['stadiumSeats'])
                    : 0,
            ]);

            $teamsByTransfermarktId[$transfermarktId] = $teamId;

            // Link to EUR competition
            DB::table('competition_teams')->updateOrInsert(
                [
                    'competition_id' => 'EUR',
                    'team_id' => $teamId,
                    'season' => $season,
                ],
                []
            );

            // Seed players
            $this->seedPlayers($data['players'] ?? [], $transfermarktId, $teamId, $valuationService);

            $imported++;
            $this->line("  Imported: {$club['name']}");
        }

        if ($imported > 0) {
            $this->info("EUR teams imported: {$imported}");
        }
    }

    private function seedPlayers(array $players, string $transfermarktId, string $teamId, PlayerValuationService $valuationService): void
    {
        foreach ($players as $player) {
            $dateOfBirth = null;
            $age = null;

            if (! empty($player['dateOfBirth'])) {
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
        }
    }
}
