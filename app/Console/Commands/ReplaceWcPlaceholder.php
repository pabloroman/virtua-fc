<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Support\CountryCodeMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

        // Regenerate templates for the replacement nation so its squad shows
        // up in fresh games. Existing games retain their pre-replacement
        // roster (templates are read at game-creation time only).
        if ($transfermarktId) {
            $jsonPath = base_path("data/2025/WC2026/teams/{$transfermarktId}.json");
            if (file_exists($jsonPath)) {
                $service = app(\App\Modules\Season\Services\GamePlayerTemplateService::class);
                $count = $service->generateForWorldCup('2025');
                $this->info("Regenerated WC templates ({$count} rows)");
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
