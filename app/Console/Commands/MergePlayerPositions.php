<?php

namespace App\Console\Commands;

use App\Support\PlayerPositionsData;
use Illuminate\Console\Command;

/**
 * Merge a `player_positions.json` downloaded from the browser scraper into the
 * curated map, and record what was attempted in the ledger CSV.
 *
 * Merging, never replacing: the map is keyed by transfermarkt id with no season
 * scoping, so an entry for a player who has since left the league is still live
 * data for him in his new one. Dropping the ~180 "stale" entries each refresh
 * would quietly lose them.
 *
 * --attempted is what keeps the next refresh honest. The download contains only
 * players that *have* a secondary position, so ids that came back empty must be
 * recorded separately or they get re-scraped every single season.
 */
class MergePlayerPositions extends Command
{
    protected $signature = 'app:merge-player-positions
                            {file : player_positions.json downloaded from the scraper}
                            {--suffix=ES : Which data/players/player_positions_{suffix}.json to merge into}
                            {--attempted= : CSV of ids sent to the scraper; appended to the ledger}
                            {--ledger= : Ledger CSV to update (default: the ES ledger)}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Merge scraped secondary positions into the curated map and update the scrape ledger';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (!file_exists($file)) {
            $this->error("Download not found: {$file}");

            return self::FAILURE;
        }

        $incoming = PlayerPositionsData::readPositions($file);
        if ($incoming === []) {
            $this->error("No usable entries in {$file} — expected a list of {id, positions}.");

            return self::FAILURE;
        }

        $target = PlayerPositionsData::positionsPath((string) $this->option('suffix'));
        $existing = PlayerPositionsData::readPositions($target);

        $added = $changed = 0;
        $merged = $existing;

        foreach ($incoming as $id => $positions) {
            if ($positions === []) {
                continue;
            }
            if (!isset($merged[$id])) {
                $added++;
            } elseif ($merged[$id] !== $positions) {
                $changed++;
            }
            $merged[$id] = $positions;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[dry run] ' : '') . 'Positions map ' . $this->relative($target));
        $this->line('  entries before : ' . count($existing));
        $this->line('  new            : ' . $added);
        $this->line('  updated        : ' . $changed);
        $this->line('  entries after  : ' . count($merged));

        if (!$dryRun) {
            PlayerPositionsData::writePositions($target, $merged);
        }

        $this->updateLedger($dryRun, array_keys($incoming));

        return self::SUCCESS;
    }

    /**
     * Fold the attempted ids into the ledger. Falls back to the download's own
     * ids when --attempted is absent, with a warning: that under-records the
     * batch by every player who had no secondary position, so those ids come
     * back as "pending" on the next refresh.
     *
     * @param  list<string>  $downloadedIds
     */
    private function updateLedger(bool $dryRun, array $downloadedIds): void
    {
        $ledger = $this->option('ledger') ?: PlayerPositionsData::ledgerPath();
        $before = PlayerPositionsData::readLedger($ledger);

        $attemptedOption = $this->option('attempted');
        if ($attemptedOption === null) {
            $this->newLine();
            $this->warn('No --attempted CSV given: recording only the ids that came back with a');
            $this->warn('position. Players scraped with no secondary position stay "pending" and');
            $this->warn('will be re-scraped next season.');
            $attempted = $downloadedIds;
        } else {
            $attempted = PlayerPositionsData::readLedger((string) $attemptedOption);
            if ($attempted === []) {
                $this->error("No ids found in {$attemptedOption} — ledger left untouched.");

                return;
            }
        }

        $after = array_values(array_unique([...$before, ...$attempted]));

        $this->newLine();
        $this->info(($dryRun ? '[dry run] ' : '') . 'Scrape ledger ' . $this->relative($ledger));
        $this->line('  ids before     : ' . count($before));
        $this->line('  newly recorded : ' . (count($after) - count($before)));
        $this->line('  ids after      : ' . count($after));

        if (!$dryRun) {
            PlayerPositionsData::writeIdCsv($ledger, $after);
        }
    }

    private function relative(string $path): string
    {
        return str_replace(base_path() . '/', '', $path);
    }
}
