<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\SeasonData;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Scaffold a new season's data folder from a previous one.
 *
 * Creates `data/{season}/{COMP}/` for every competition declared in
 * config/countries.php and bootstraps each `schedule.json` by copying the
 * source season's calendar with every date shifted forward by the year
 * difference. Squad data (`teams.json` and EUR/INT pool files) is *not*
 * generated — those come from the scraper — so the command finishes by
 * printing a checklist of the squad files still missing for the new season.
 *
 * Typical yearly use:
 *   php artisan app:scaffold-season 2026
 *   # drop scraped teams.json / pool files into data/2026/*
 *   php artisan app:validate-season 2026
 *   php artisan app:seed-reference-data --fresh
 */
class ScaffoldSeason extends Command
{
    protected $signature = 'app:scaffold-season
                            {season : Target season to scaffold (e.g. 2026)}
                            {--from= : Source season to copy from (defaults to season - 1)}
                            {--force : Overwrite schedule.json files that already exist}';

    protected $description = 'Scaffold a new season data folder, bootstrapping schedules from the previous season';

    public function handle(CountryConfig $countryConfig): int
    {
        $season = $this->argument('season');
        $from = $this->option('from') ?: (string) ((int) $season - 1);

        if (!ctype_digit($season) || !ctype_digit($from)) {
            $this->error('Season and --from must be numeric years (e.g. 2026).');
            return self::FAILURE;
        }

        $yearDiff = (int) $season - (int) $from;
        if ($yearDiff === 0) {
            $this->error('Target season and --from cannot be the same.');
            return self::FAILURE;
        }

        $fromBase = base_path("data/{$from}");
        if (!is_dir($fromBase)) {
            $this->error("Source season folder not found: {$fromBase}");
            return self::FAILURE;
        }

        $this->info("Scaffolding data/{$season} from data/{$from} (shift {$yearDiff}y)...");
        $this->newLine();

        $missing = [];
        $shifted = 0;

        foreach ($this->competitions($countryConfig) as $code => $needs) {
            $srcDir = "{$fromBase}/{$code}";
            $dstDir = base_path("data/{$season}/{$code}");

            if (!is_dir($dstDir) && !mkdir($dstDir, 0o755, true) && !is_dir($dstDir)) {
                $this->error("  Could not create {$dstDir}");
                return self::FAILURE;
            }

            // Bootstrap schedule.json by shifting source dates forward.
            $srcSchedule = "{$srcDir}/schedule.json";
            $dstSchedule = "{$dstDir}/schedule.json";
            if (file_exists($srcSchedule)) {
                if (file_exists($dstSchedule) && !$this->option('force')) {
                    $this->line("  {$code}: schedule.json exists (skipped; --force to overwrite)");
                } else {
                    $data = json_decode(file_get_contents($srcSchedule), true);
                    $shiftedData = $this->shiftDates($data, $yearDiff);
                    file_put_contents($dstSchedule, json_encode($shiftedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                    $this->line("  {$code}: schedule.json shifted {$yearDiff}y → data/{$season}/{$code}/");
                    $shifted++;
                }
            }

            // Record squad data still needed from the scraper.
            if ($needs === 'teams') {
                if (!file_exists("{$dstDir}/teams.json")) {
                    $missing[] = "data/{$season}/{$code}/teams.json";
                }
            } elseif ($needs === 'pool') {
                $existing = glob("{$dstDir}/*.json") ?: [];
                $existing = array_filter($existing, fn ($p) => basename($p) !== 'schedule.json');
                if (count($existing) === 0) {
                    $missing[] = "data/{$season}/{$code}/*.json (per-team pool files)";
                }
            }
        }

        $this->newLine();
        $this->info("Schedules bootstrapped: {$shifted}");

        if (empty($missing)) {
            $this->info('All squad data is present. Ready to validate & seed.');
        } else {
            $this->newLine();
            $this->warn('Squad data still needed from the scraper (set "seasonID": "' . $season . '" in teams.json):');
            foreach ($missing as $path) {
                $this->line("  - {$path}");
            }
            $this->newLine();
            $this->line('Also append any new players to data/players/player_positions_ES.json.');
        }

        return self::SUCCESS;
    }

    /**
     * Map every competition that owns a data/{season}/ folder to the squad data
     * it needs from the scraper: 'teams' (a teams.json — leagues, cups and
     * continental participant lists), 'pool' (per-team {id}.json files for the
     * EUR/INT pools), or 'none' (bare playoff — schedule only). Derived from the
     * shared SeasonData enumerator so it stays in lockstep with validate/seed.
     *
     * @return array<string, string>
     */
    private function competitions(CountryConfig $countryConfig): array
    {
        $needsByType = [
            'league' => 'teams',
            'cup' => 'teams',
            'continental' => 'teams',
            'pool' => 'pool',
            'none' => 'none',
        ];

        $out = [];
        foreach (SeasonData::competitions($countryConfig) as ['code' => $code, 'type' => $type]) {
            $out[$code] = $needsByType[$type];
        }

        return $out;
    }

    /**
     * Recursively shift every YYYY-MM-DD value (under any key) forward by
     * $yearDiff years, preserving all other fields and structure.
     */
    private function shiftDates(mixed $value, int $yearDiff): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->shiftDates($v, $yearDiff);
            }
            return $out;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::parse($value)->addYears($yearDiff)->format('Y-m-d');
        }

        return $value;
    }
}
