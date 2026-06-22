<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class BuildSofascoreIdMap extends Command
{
    protected $signature = 'app:build-sofascore-id-map
                            {--season= : Season year (defaults to config season.current)}';

    protected $description = 'Extract a Transfermarkt-id → Sofascore-id map from data/{season}/people.csv into data/{season}/sofascore_ids.json';

    public function handle(): int
    {
        $season = $this->option('season') ?: config('season.current');

        $sourcePath = base_path("data/{$season}/people.csv");
        if (!file_exists($sourcePath)) {
            $this->error("Crosswalk not found: {$sourcePath}");
            $this->line('people.csv is the untracked raw source — place it under data/{season}/ before running.');

            return CommandAlias::FAILURE;
        }

        $handle = fopen($sourcePath, 'r');
        if ($handle === false) {
            $this->error("Could not open {$sourcePath}");

            return CommandAlias::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('people.csv is empty.');

            return CommandAlias::FAILURE;
        }

        // Resolve column positions by header name so the map survives column
        // reordering in future crosswalk exports.
        $tmIndex = array_search('key_transfermarkt', $header, true);
        $sofascoreIndex = array_search('key_sofascore', $header, true);
        if ($tmIndex === false || $sofascoreIndex === false) {
            fclose($handle);
            $this->error('people.csv is missing key_transfermarkt and/or key_sofascore columns.');

            return CommandAlias::FAILURE;
        }

        $map = [];
        $rowsScanned = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowsScanned++;
            $transfermarktId = trim((string) ($row[$tmIndex] ?? ''));
            $sofascoreId = trim((string) ($row[$sofascoreIndex] ?? ''));
            if ($transfermarktId === '' || $sofascoreId === '') {
                continue;
            }
            $map[$transfermarktId] = $sofascoreId;
        }
        fclose($handle);

        // Sort by Transfermarkt id for stable, reviewable diffs.
        ksort($map);

        $outputPath = base_path("data/{$season}/sofascore_ids.json");
        file_put_contents(
            $outputPath,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $this->info("Wrote {$outputPath}");
        $this->line('  Rows scanned: ' . number_format($rowsScanned));
        $this->line('  Pairs written: ' . number_format(count($map)));

        return CommandAlias::SUCCESS;
    }
}
