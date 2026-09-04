<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\PlayerPositionsData;
use App\Support\SeasonData;
use Illuminate\Console\Command;

/**
 * Emit the list of players in a season's squads that the secondary-positions
 * scraper has never been pointed at, as a CSV the browser extension can load.
 *
 * The pairing with app:merge-player-positions matters. The scraper only
 * downloads players that turned out to *have* a secondary position, so
 * `player_positions_*.json` cannot tell "scraped, has none" apart from "never
 * scraped". The ledger CSV is the record of what was attempted, and it is what
 * this command diffs against — not the JSON. Without it, every refresh
 * re-scrapes the ~24% of players who legitimately have no secondary position.
 */
class ListMissingPlayerPositions extends Command
{
    protected $signature = 'app:list-missing-positions
                            {season : Season to scan (e.g. 2026)}
                            {--country=ES : Country whose league tiers to scan, when no --competition is given}
                            {--competition=* : Explicit competition codes to scan (e.g. ENG1), overriding --country}
                            {--ledger= : CSV of already-attempted ids (default: the ES ledger)}
                            {--output= : Where to write the CSV (default: the generated todo list the extension reads)}
                            {--all : Ignore the ledger and list every player, for a full re-scrape}';

    protected $description = 'List players with no secondary-position scrape attempt yet, as a CSV for the browser scraper';

    public function handle(CountryConfig $countryConfig): int
    {
        $season = $this->argument('season');

        if (!is_dir(base_path("data/{$season}"))) {
            $this->error("Season folder not found: data/{$season}");

            return self::FAILURE;
        }

        $codes = $this->resolveCompetitions($countryConfig);
        if ($codes === []) {
            $this->error('No competitions to scan . ');

            return self::FAILURE;
        }

        $ledgerPath = $this->option('ledger') ?: PlayerPositionsData::ledgerPath();
        $attempted = $this->option('all') ? [] : PlayerPositionsData::readLedger($ledgerPath);
        $known = PlayerPositionsData::knownIds();

        $todo = [];
        $rows = [];

        foreach ($codes as $code) {
            $clubs = SeasonData::readCompetitionClubs($season, $code, 'league');
            if ($clubs === null) {
                $this->warn("  {$code}: no squad data, skipped.");

                continue;
            }

            $ids = [];
            foreach ($clubs as $club) {
                foreach (array_keys($club['players']) as $playerId) {
                    $ids[$playerId] = true;
                }
            }
            $ids = array_keys($ids);

            $pending = array_values(array_diff($ids, $attempted));
            foreach ($pending as $playerId) {
                $todo[$playerId] = true;
            }

            $rows[] = [
                $code,
                count($ids),
                count($ids) - count($pending),
                count($ids) === 0 ? '—' : round(100 * (count($ids) - count($pending)) / count($ids)) . '%',
                count(array_intersect($ids, $known)),
                count($pending),
            ];
        }

        $this->table(
            ['competition', 'players', 'attempted', '%', 'with entry', 'pending'],
            $rows,
        );

        $todo = array_keys($todo);

        if ($todo === []) {
            $this->info('Every player has been through the scraper — nothing to do . ');

            return self::SUCCESS;
        }

        $output = $this->option('output') ?: PlayerPositionsData::todoPath();
        PlayerPositionsData::writeIdCsv($output, $todo);

        $this->newLine();
        $this->info(count($todo) . ' player(s) pending → ' . $this->relative($output));
        $this->line('Point the extension\'s "Batch player positions" list at that file, run it,');
        $this->line('then merge the download with:');
        $this->newLine();
        $this->line('  php artisan app:merge-player-positions ~/Downloads/player_positions.json --attempted=' . $this->relative($output));

        return self::SUCCESS;
    }

    /**
     * Competition codes to scan: explicit --competition wins, else every league
     * tier of --country (ESP1/ESP2/ESP3A/ESP3B for ES).
     *
     * @return list<string>
     */
    private function resolveCompetitions(CountryConfig $countryConfig): array
    {
        $explicit = (array) $this->option('competition');
        if ($explicit !== []) {
            return array_values(array_filter(array_map('strval', $explicit)));
        }

        $country = (string) $this->option('country');

        return array_values(array_filter(array_map(
            fn (array $tier): string => (string) ($tier['competition'] ?? ''),
            $countryConfig->flattenedTiers($country),
        )));
    }

    private function relative(string $path): string
    {
        return str_replace(base_path() . '/', '', $path);
    }
}
