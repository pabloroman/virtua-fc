<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Modules\Player\Services\PlayerValuationService;
use App\Support\CountryCodeMapper;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ReplaceWcPlaceholder extends Command
{
    protected $signature = 'app:replace-wc-placeholder
                            {--placeholder-code= : FIFA code of the placeholder to replace (e.g., UEPA)}
                            {--new-name= : Name of the real team (e.g., Turkey)}
                            {--new-fifa-code= : FIFA code of the real team (e.g., TUR)}
                            {--transfermarkt-id= : Transfermarkt ID if roster JSON is available}';

    protected $description = 'Replace a World Cup placeholder team with a real qualified team';

    public function handle(): int
    {
        $placeholderCode = $this->option('placeholder-code');
        $newName = $this->option('new-name');
        $newFifaCode = $this->option('new-fifa-code');
        $transfermarktId = $this->option('transfermarkt-id');

        if (!$placeholderCode || !$newName || !$newFifaCode) {
            $this->error('All of --placeholder-code, --new-name, and --new-fifa-code are required.');
            return CommandAlias::FAILURE;
        }

        // Look up placeholder team from the database
        $team = Team::where('fifa_code', $placeholderCode)
            ->where('is_placeholder', true)
            ->first();

        if (!$team) {
            $this->error("Placeholder team with FIFA code '{$placeholderCode}' not found.");
            return CommandAlias::FAILURE;
        }

        $oldName = $team->getRawOriginal('name');
        $countryCode = CountryCodeMapper::toCode($newName);

        // Update Team record
        $updateData = [
            'name' => $newName,
            'country' => $countryCode,
            'fifa_code' => $newFifaCode,
            'is_placeholder' => false,
        ];
        if ($transfermarktId) {
            $updateData['transfermarkt_id'] = $transfermarktId;
        }

        $team->update($updateData);
        $this->info("Updated team: {$oldName} → {$newName}");

        // Seed players if roster JSON exists
        if ($transfermarktId) {
            $jsonPath = base_path("data/2025/WC2026/teams/{$transfermarktId}.json");
            if (file_exists($jsonPath)) {
                $playerCount = $this->seedPlayers($jsonPath);
                $this->info("Seeded {$playerCount} players from {$transfermarktId}.json");
            } else {
                $this->warn("No roster file found at data/2025/WC2026/teams/{$transfermarktId}.json");
            }
        }

        // Regenerate groups.json with new FIFA code
        $this->regenerateGroupsJson($placeholderCode, $newFifaCode);

        $this->newLine();
        $this->info('Placeholder replacement complete!');
        $this->line('Existing games: Team name/flag will auto-update. Squad unchanged.');
        $this->line('New games: Will use real roster if available.');

        return CommandAlias::SUCCESS;
    }

    private function seedPlayers(string $jsonPath): int
    {
        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data || empty($data['players'])) {
            return 0;
        }

        $valuationService = app(PlayerValuationService::class);
        $count = 0;

        foreach ($data['players'] as $player) {
            $transfermarktId = $player['id'] ?? null;
            if (!$transfermarktId) {
                continue;
            }

            if (DB::table('players')->where('transfermarkt_id', $transfermarktId)->exists()) {
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

    private function regenerateGroupsJson(string $oldCode, string $newCode): void
    {
        $groupsPath = base_path('data/2025/WC2026/groups.json');
        $content = file_get_contents($groupsPath);

        // Replace old FIFA code with new one in groups.json
        $content = str_replace(
            ["\"{$oldCode}\""],
            ["\"{$newCode}\""],
            $content
        );

        file_put_contents($groupsPath, $content);
        $this->info("Updated groups.json: {$oldCode} → {$newCode}");
    }
}
