<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class BuildSofascoreIdMap extends Command
{
    protected $signature = 'app:build-sofascore-id-map';

    protected $description = 'Build data/sofascore_ids.json from data/raw/people.csv, layering data/sofascore_ids_overrides.csv on top';

    public function handle(): int
    {
        $sourcePath = $this->resolveCrosswalkPath();
        if ($sourcePath === null) {
            $this->error('Crosswalk not found.');
            $this->line('people.csv is the untracked raw source — place it at data/raw/people.csv before running.');

            return CommandAlias::FAILURE;
        }

        // Silenced: Laravel promotes the fopen warning to an ErrorException, which
        // would bypass the graceful failure right below it.
        $handle = @fopen($sourcePath, 'r');
        if ($handle === false) {
            $this->error("Could not open {$sourcePath}");

            return CommandAlias::FAILURE;
        }

        $header = $this->readRow($handle);
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

        // people.csv is a ~445k-row crosswalk; show progress so a multi-second
        // scan doesn't look like a hang. Redraw is throttled so rendering the
        // bar costs almost nothing relative to the scan itself.
        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% rows scanned [%bar%] %elapsed:6s%');
        $bar->setRedrawFrequency(5000);
        $bar->start();

        while (($row = $this->readRow($handle)) !== false) {
            $rowsScanned++;
            $bar->advance();
            $transfermarktId = trim((string) ($row[$tmIndex] ?? ''));
            $sofascoreId = trim((string) ($row[$sofascoreIndex] ?? ''));
            if ($transfermarktId === '' || $sofascoreId === '') {
                continue;
            }
            $map[$transfermarktId] = $sofascoreId;
        }

        $bar->finish();
        $this->newLine();
        fclose($handle);

        // Layer manual overrides on top. The overrides file is tracked in git and
        // is never touched by the people.csv provider import, so hand-mapped ids
        // (crosswalk blanks, or corrections to a wrong id) survive every re-import.
        [$overridesApplied, $overrideConflicts, $overridesDisplayPath] = $this->applyOverrides($map);

        // Sort by Transfermarkt id for stable, reviewable diffs.
        ksort($map);

        $outputPath = base_path('data/sofascore_ids.json');
        file_put_contents(
            $outputPath,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $this->info("Wrote {$outputPath}");
        $this->line('  Rows scanned: ' . number_format($rowsScanned));
        if ($overridesDisplayPath !== null) {
            $this->line('  Overrides applied: ' . number_format($overridesApplied) . " (from {$overridesDisplayPath})");
            if ($overrideConflicts > 0) {
                $this->warn('  Overrides that changed an existing crosswalk value: ' . number_format($overrideConflicts));
            }
        }
        $this->line('  Pairs written: ' . number_format(count($map)));

        return CommandAlias::SUCCESS;
    }

    /**
     * fgetcsv() wrapper that pins the $escape argument. PHP 8.4+ deprecates
     * calling fgetcsv() without it (the default is changing from "\\" to ""),
     * and this command scans a ~445k-row crosswalk — one deprecation notice per
     * row, each routed through the framework error handler, dominated the
     * runtime. Pinning the historical "\\" both silences the notice and keeps
     * parsing byte-identical to prior runs (no surprise diffs in the output).
     *
     * @param  resource  $handle
     * @return list<string|null>|false
     */
    private function readRow($handle): array|false
    {
        return fgetcsv($handle, null, ',', '"', '\\');
    }

    /**
     * Locate the untracked crosswalk. The raw import drops it under data/raw/;
     * data/people.csv is accepted as a convenience for a hand-placed file.
     */
    private function resolveCrosswalkPath(): ?string
    {
        foreach ([
            base_path('data/raw/people.csv'),
            base_path('data/people.csv'),
        ] as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Layer manual Transfermarkt-id → Sofascore-id overrides on top of the
     * crosswalk-derived map. Overrides win, so this also corrects a wrong id,
     * not just fills a blank one. Rows whose first cell starts with '#' are
     * treated as comments.
     *
     * @param  array<string, string>  $map
     * @return array{0: int, 1: int, 2: ?string}  [applied, conflicts, displayPath]
     */
    private function applyOverrides(array &$map): array
    {
        $displayPath = 'data/sofascore_ids_overrides.csv';
        $path = base_path($displayPath);
        if (!file_exists($path)) {
            return [0, 0, null];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->warn("Could not open overrides file {$displayPath} — skipping.");

            return [0, 0, null];
        }

        $header = $this->readRow($handle);
        if ($header === false) {
            fclose($handle);

            return [0, 0, $displayPath];
        }

        $tmIndex = array_search('key_transfermarkt', $header, true);
        $sofascoreIndex = array_search('key_sofascore', $header, true);
        if ($tmIndex === false || $sofascoreIndex === false) {
            fclose($handle);
            $this->warn("Overrides file {$displayPath} is missing key_transfermarkt and/or key_sofascore columns — skipping.");

            return [0, 0, $displayPath];
        }

        $applied = 0;
        $conflicts = 0;
        while (($row = $this->readRow($handle)) !== false) {
            // Allow "# ..." comment lines in the overrides file.
            if (str_starts_with(ltrim((string) ($row[0] ?? '')), '#')) {
                continue;
            }
            $transfermarktId = trim((string) ($row[$tmIndex] ?? ''));
            $sofascoreId = trim((string) ($row[$sofascoreIndex] ?? ''));
            if ($transfermarktId === '' || $sofascoreId === '') {
                continue;
            }
            if (isset($map[$transfermarktId]) && $map[$transfermarktId] !== $sofascoreId) {
                $this->warn("  Override changes tm {$transfermarktId}: {$map[$transfermarktId]} → {$sofascoreId}");
                $conflicts++;
            }
            $map[$transfermarktId] = $sofascoreId;
            $applied++;
        }
        fclose($handle);

        return [$applied, $conflicts, $displayPath];
    }
}
