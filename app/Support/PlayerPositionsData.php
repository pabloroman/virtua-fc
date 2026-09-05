<?php

namespace App\Support;

/**
 * Shared primitives for the secondary-positions data files.
 *
 * Three artefacts move together and are easy to get out of step:
 *  - `data/players/player_positions_{SUFFIX}.json` — the curated map consumed
 *    by GamePlayerTemplateService, keyed by transfermarkt id and *not* season
 *    scoped (a player who leaves the league keeps his positions),
 *  - `scripts/transfermarkt-scraper/{...}-player-ids.csv` — the ledger of ids
 *    the scraper has been pointed at, and
 *  - the todo CSV the extension loads for its next batch.
 *
 * The ledger exists because the scraper only downloads players that *have* a
 * secondary position, so the JSON alone cannot distinguish "scraped, has none"
 * from "never scraped".
 */
class PlayerPositionsData
{
    /** Ledger of every id the ES scrape has been pointed at. */
    public static function ledgerPath(): string
    {
        return base_path('scripts/transfermarkt-scraper/esp-player-ids.csv');
    }

    /** Generated batch list the extension reads; regenerated per refresh. */
    public static function todoPath(): string
    {
        return base_path('scripts/transfermarkt-scraper/player-ids-todo.csv');
    }

    public static function positionsPath(string $suffix): string
    {
        return base_path("data/players/player_positions_{$suffix}.json");
    }

    /**
     * Read a one-id-per-line CSV, skipping the header and anything non-numeric.
     *
     * @return list<string>
     */
    public static function readLedger(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $lines = preg_split('/\R/', (string) file_get_contents($path)) ?: [];

        return array_values(array_unique(array_filter(
            array_map('trim', $lines),
            fn (string $line): bool => $line !== '' && ctype_digit($line),
        )));
    }

    /**
     * Write ids as a headered CSV, numerically sorted so re-runs diff cleanly.
     *
     * @param  list<string>  $ids
     */
    public static function writeIdCsv(string $path, array $ids): void
    {
        $ids = array_values(array_unique($ids));
        usort($ids, fn (string $a, string $b): int => (int) $a <=> (int) $b);

        file_put_contents($path, "transfermarkt_id\n".implode("\n", $ids)."\n");
    }

    /**
     * Every id that already carries secondary positions, across all files —
     * matching how GamePlayerTemplateService globs them.
     *
     * @return list<string>
     */
    public static function knownIds(): array
    {
        $ids = [];

        foreach (glob(base_path('data/players/player_positions_*.json')) ?: [] as $file) {
            foreach (self::readPositions($file) as $id => $_) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Read a positions file into an id => positions map.
     *
     * @return array<string, list<string>>
     */
    public static function readPositions(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $entries = json_decode((string) file_get_contents($path), true);
        if (! is_array($entries)) {
            return [];
        }

        $map = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || empty($entry['id'])) {
                continue;
            }
            $map[(string) $entry['id']] = array_values(array_filter(
                (array) ($entry['positions'] ?? []),
                fn ($position): bool => is_string($position) && $position !== '',
            ));
        }

        return $map;
    }

    /**
     * Write an id => positions map back in the scraper's on-disk shape:
     * a list of {id, positions}, id-sorted, 2-space indented.
     *
     * @param  array<string, list<string>>  $map
     */
    public static function writePositions(string $path, array $map): void
    {
        uksort($map, fn (string $a, string $b): int => (int) $a <=> (int) $b);

        $entries = [];
        foreach ($map as $id => $positions) {
            // PHP silently casts numeric-string array keys to int, so the id has
            // to be cast back or it lands in the JSON as a number and every id
            // in the file changes shape.
            $entries[] = ['id' => (string) $id, 'positions' => $positions];
        }

        file_put_contents($path, SeasonData::encode($entries));
    }
}
