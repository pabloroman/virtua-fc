<?php

namespace App\Console\Commands;

use App\Game\Services\InjuryService;
use App\Models\GamePlayer;
use Illuminate\Console\Command;

class BackfillPlayerDurability extends Command
{
    protected $signature = 'game:backfill-durability {--dry-run : Show distribution without making changes}';

    protected $description = 'Backfill durability attribute for existing game players';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $players = GamePlayer::whereNull('durability')
            ->orWhere('durability', 50) // Also regenerate default values
            ->get();

        if ($players->isEmpty()) {
            $this->info('All players already have durability values set.');
            return 0;
        }

        $this->info("Processing {$players->count()} players...");

        $distribution = [
            'Very Injury Prone (1-20)' => 0,
            'Injury Prone (21-40)' => 0,
            'Average (41-60)' => 0,
            'Resilient (61-80)' => 0,
            'Ironman (81-100)' => 0,
        ];

        $bar = $this->output->createProgressBar($players->count());

        foreach ($players as $player) {
            $durability = InjuryService::generateDurability();

            // Track distribution
            if ($durability <= 20) $distribution['Very Injury Prone (1-20)']++;
            elseif ($durability <= 40) $distribution['Injury Prone (21-40)']++;
            elseif ($durability <= 60) $distribution['Average (41-60)']++;
            elseif ($durability <= 80) $distribution['Resilient (61-80)']++;
            else $distribution['Ironman (81-100)']++;

            if (!$dryRun) {
                $player->update(['durability' => $durability]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Show distribution
        $this->info('Durability Distribution:');
        $this->table(
            ['Category', 'Count', 'Percentage'],
            collect($distribution)->map(function ($count, $category) use ($players) {
                return [
                    $category,
                    $count,
                    round(($count / $players->count()) * 100, 1) . '%',
                ];
            })->toArray()
        );

        if ($dryRun) {
            $this->warn('Dry run - no changes made. Run without --dry-run to apply.');
        } else {
            $this->info("Updated {$players->count()} players with durability values.");
        }

        return 0;
    }
}
