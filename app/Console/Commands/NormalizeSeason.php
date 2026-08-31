<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\SeasonData;
use Illuminate\Console\Command;

/**
 * Rewrite a season's squad files (`teams.json` + EUR/INT pool files) into their
 * canonical on-disk form so that re-scrapes produce minimal, reviewable diffs.
 *
 * For every squad file it:
 *  - forces `seasonID` to the target season (kills the recurring "forgot to set
 *    seasonID" foot-gun on teams.json),
 *  - sorts clubs by transfermarkt id and each club's players by player id, so a
 *    transfer shows up as a one-player add/remove instead of reshuffling the
 *    whole roster, and
 *  - backfills a club's `country` from elsewhere in the same season folder (see
 *    buildCountryIndex), and
 *  - re-encodes with the canonical formatter (2-space, unescaped, trailing nl).
 *
 * Idempotent: running it twice is a no-op. With `--check` it writes nothing and
 * exits non-zero if any file is not already canonical — the gate the season-data
 * CI workflow uses.
 *
 * Schedules are intentionally left untouched (they are owned by
 * app:scaffold-season), so a squad-only refresh stays squad-only in the diff.
 */
class NormalizeSeason extends Command
{
    protected $signature = 'app:normalize-season
                            {season : Season to normalize (e.g. 2026)}
                            {--check : Report non-canonical files and exit non-zero without writing}';

    protected $description = 'Canonicalize a season data folder (force seasonID, sort clubs/players, stable formatting)';

    public function handle(CountryConfig $countryConfig): int
    {
        $season = $this->argument('season');
        $base = base_path("data/{$season}");

        if (!is_dir($base)) {
            $this->error("Season folder not found: {$base}");
            return self::FAILURE;
        }

        $check = (bool) $this->option('check');
        $this->info(($check ? 'Checking' : 'Normalizing') . " data/{$season}...");

        $changed = [];
        $competitions = SeasonData::competitions($countryConfig);
        $countries = $this->buildCountryIndex($season, $competitions, $countryConfig);

        foreach ($competitions as ['code' => $code, 'type' => $type]) {
            $dir = "{$base}/{$code}";

            if ($type === 'pool') {
                $files = array_filter(glob("{$dir}/*.json") ?: [], fn ($p) => basename($p) !== 'schedule.json');
                foreach ($files as $file) {
                    $this->process($file, $season, $type, $countries, $check, $changed);
                }
                continue;
            }

            if ($type !== 'none') {
                $this->process("{$dir}/teams.json", $season, $type, $countries, $check, $changed);
            }
        }

        $this->newLine();

        if (empty($changed)) {
            $this->info($check ? 'All squad files are canonical.' : 'Nothing to normalize — already canonical.');
            return self::SUCCESS;
        }

        if ($check) {
            $this->error(count($changed) . ' file(s) are not canonical:');
            foreach ($changed as $path) {
                $this->line("  ✗ {$path}");
            }
            $this->newLine();
            $this->line('Run `php artisan app:normalize-season ' . $season . '` to fix.');
            return self::FAILURE;
        }

        $this->info('Normalized ' . count($changed) . ' file(s):');
        foreach ($changed as $path) {
            $this->line("  ✓ {$path}");
        }
        return self::SUCCESS;
    }

    /**
     * Normalize one squad file. Records its repo-relative path in $changed when
     * its canonical form differs from disk; writes it unless in --check mode.
     *
     * @param  array<string, string>  $countries
     * @param  array<int, string>  $changed
     */
    private function process(
        string $path,
        string $season,
        string $type,
        array $countries,
        bool $check,
        array &$changed,
    ): void {
        if (!file_exists($path)) {
            return;
        }

        $original = (string) file_get_contents($path);
        $data = json_decode($original, true);
        if (!is_array($data)) {
            $this->warn('  Skipping unparsable JSON: ' . $this->relative($path));
            return;
        }

        $canonical = SeasonData::encode($this->canonicalize($data, $season, $type, $countries));

        if ($canonical === $original) {
            return;
        }

        $changed[] = $this->relative($path);
        if (!$check) {
            file_put_contents($path, $canonical);
        }
    }

    /**
     * Produce the canonical structure: forced seasonID (teams files only, hoisted
     * just after id/name), backfilled country, clubs sorted by transfermarkt id,
     * players sorted by player id. All other keys and their order are preserved.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $countries
     * @return array<string, mixed>
     */
    private function canonicalize(array $data, string $season, string $type, array $countries): array
    {
        if ($type !== 'pool') {
            $data['seasonID'] = $season;
            $data = $this->hoistKeys($data, ['id', 'name', 'seasonID']);
        }

        if (isset($data['clubs']) && is_array($data['clubs'])) {
            $data['clubs'] = $this->sortById($data['clubs'], fn ($c) => SeasonData::resolveTransfermarktId($c));
            $data['clubs'] = array_map(fn ($club) => $this->sortPlayers($club), $data['clubs']);

            if ($type === 'continental') {
                $data['clubs'] = array_map(fn ($club) => $this->withCountry($club, $countries), $data['clubs']);
            }
        } elseif ($type === 'pool') {
            // Pool files store a single club at the top level.
            $data = $this->withCountry($this->sortPlayers($data), $countries);
        } else {
            $data = $this->sortPlayers($data);
        }

        return $data;
    }

    /**
     * Fill in a club's `country` from the season index when the club itself
     * doesn't declare one. Never overwrites an existing value, so a hand-curated
     * country always wins.
     *
     * @param  array<string, mixed>  $club
     * @param  array<string, string>  $countries
     * @return array<string, mixed>
     */
    private function withCountry(array $club, array $countries): array
    {
        if (!empty($club['country'])) {
            return $club;
        }

        $id = SeasonData::resolveTransfermarktId($club);
        if ($id === null || !isset($countries[$id])) {
            return $club;
        }

        $club['country'] = $countries[$id];

        // Land the key where the curated files already put it — `id, name,
        // country, pot` for a continental club, `image, name, country, ...` for
        // a pool file — so a re-scrape of a backfilled file has no key-order
        // churn to diff. hoistKeys skips whichever id key this shape lacks.
        return $this->hoistKeys($club, ['id', 'image', 'name', 'country']);
    }

    /**
     * Map every transfermarkt id in this season folder to a country, gathering
     * from all three places one can be stated:
     *  - a league's teams.json — the country is the competition's, from config,
     *  - an EUR/INT pool file — its own `country` key,
     *  - a continental participant list — its per-club `country`.
     *
     * The last two repair each other. The scraper emits neither for continental
     * lists, and (before it learned to read the club page's flag) omitted it on
     * many pool files too — leaving those clubs to seed with the pool's default
     * `EU`. That matters: Team::country drives the Swiss draw's country
     * protection from the second season onward, and a large bloc of clubs
     * sharing one country makes the constraint unsatisfiable, forcing the draw
     * to relax it for everyone.
     *
     * @param  array<int, array{code: string, type: string}>  $competitions
     * @return array<string, string>
     */
    private function buildCountryIndex(string $season, array $competitions, CountryConfig $countryConfig): array
    {
        $countries = [];

        foreach ($competitions as ['code' => $code, 'type' => $type]) {
            if ($type === 'league') {
                $country = $countryConfig->countryForCompetition($code);
                if ($country === null) {
                    continue;
                }
                foreach ($this->readClubs(base_path("data/{$season}/{$code}/teams.json")) as $club) {
                    $id = SeasonData::resolveTransfermarktId($club);
                    if ($id !== null) {
                        $countries[$id] ??= $country;
                    }
                }
                continue;
            }

            if ($type === 'continental') {
                foreach ($this->readClubs(base_path("data/{$season}/{$code}/teams.json")) as $club) {
                    $id = SeasonData::resolveTransfermarktId($club);
                    if ($id !== null && !empty($club['country'])) {
                        $countries[$id] ??= (string) $club['country'];
                    }
                }
                continue;
            }

            if ($type === 'pool') {
                $dir = base_path("data/{$season}/{$code}");
                $files = array_filter(glob("{$dir}/*.json") ?: [], fn ($p) => basename($p) !== 'schedule.json');
                foreach ($files as $file) {
                    $data = json_decode((string) file_get_contents($file), true);
                    if (!is_array($data) || empty($data['country'])) {
                        continue;
                    }
                    $id = SeasonData::resolveTransfermarktId($data);
                    if ($id !== null) {
                        $countries[$id] ??= (string) $data['country'];
                    }
                }
            }
        }

        return $countries;
    }

    /**
     * Read a teams.json clubs array, or an empty list when the file is absent
     * or unparsable — those are reported by app:validate-season, not here.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readClubs(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && is_array($data['clubs'] ?? null) ? $data['clubs'] : [];
    }

    /**
     * Sort a club's players list by player id, leaving all other keys intact.
     *
     * @param  array<string, mixed>  $club
     * @return array<string, mixed>
     */
    private function sortPlayers(array $club): array
    {
        if (isset($club['players']) && is_array($club['players'])) {
            $club['players'] = $this->sortById($club['players'], fn ($p) => $p['id'] ?? null);
        }

        return $club;
    }

    /**
     * Stable sort a list by a (numeric) id extracted via $idOf.
     *
     * @param  array<int, mixed>  $list
     * @param  callable(mixed): (string|null)  $idOf
     * @return array<int, mixed>
     */
    private function sortById(array $list, callable $idOf): array
    {
        usort($list, fn ($a, $b) => (int) $idOf($a) <=> (int) $idOf($b));

        return $list;
    }

    /**
     * Reorder $data so the given keys (when present) lead, preserving the order
     * of the remaining keys.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function hoistKeys(array $data, array $keys): array
    {
        $ordered = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $ordered[$key] = $data[$key];
            }
        }
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $ordered)) {
                $ordered[$key] = $value;
            }
        }

        return $ordered;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
