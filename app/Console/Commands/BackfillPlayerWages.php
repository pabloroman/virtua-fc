<?php

namespace App\Console\Commands;

use App\Game\Services\ContractService;
use App\Models\Competition;
use App\Models\GamePlayer;
use App\Models\Team;
use Illuminate\Console\Command;

class BackfillPlayerWages extends Command
{
    protected $signature = 'game:backfill-wages {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill annual wages for existing game players that have no wage set';

    public function __construct(private readonly ContractService $contractService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        // Find all game players with no wage set (0 or null)
        $playersToUpdate = GamePlayer::where(function ($query) {
            $query->where('annual_wage', 0)
                ->orWhereNull('annual_wage');
        })->get();

        if ($playersToUpdate->isEmpty()) {
            $this->info('No players need wage backfill.');
            return Command::SUCCESS;
        }

        $this->info("Found {$playersToUpdate->count()} players to update.");

        // Cache team minimum wages
        $teamMinimumWages = [];

        $bar = $this->output->createProgressBar($playersToUpdate->count());
        $bar->start();

        $updated = 0;
        $examples = [];

        foreach ($playersToUpdate as $player) {
            // Get minimum wage for player's team
            if (!isset($teamMinimumWages[$player->team_id])) {
                $team = Team::find($player->team_id);
                $teamMinimumWages[$player->team_id] = $team
                    ? $this->contractService->getMinimumWageForTeam($team)
                    : 10_000_000; // €100K fallback
            }

            $minimumWage = $teamMinimumWages[$player->team_id];

            // Calculate wage with age modifier
            // Young players get rookie contract discount, veterans get legacy premium
            $annualWage = $this->contractService->calculateAnnualWage(
                $player->market_value_cents,
                $minimumWage,
                $player->age
            );

            // Collect examples for display
            if (count($examples) < 10) {
                $examples[] = [
                    'name' => $player->name,
                    'age' => $player->age,
                    'market_value' => $player->market_value,
                    'wage' => ContractService::formatWage($annualWage),
                ];
            }

            if (!$dryRun) {
                $player->update(['annual_wage' => $annualWage]);
            }

            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Show examples
        $this->info('Sample wages calculated:');
        $this->table(
            ['Player', 'Age', 'Market Value', 'Annual Wage'],
            array_map(fn($e) => [$e['name'], $e['age'], $e['market_value'], $e['wage']], $examples)
        );

        if ($dryRun) {
            $this->warn("DRY RUN: Would have updated {$updated} players.");
        } else {
            $this->info("Successfully updated {$updated} players with wages.");
        }

        return Command::SUCCESS;
    }
}
