<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\SeasonData;
use Illuminate\Console\Command;

/**
 * Validate a season's data folder before seeding.
 *
 * Read-only pre-seed gate: checks that every competition declared in
 * config/countries.php has the squad data the seeder expects, that
 * transfermarkt ids resolve, that any declared seasonID agrees with the
 * folder, and that round-robin league schedules have the exact number of
 * rounds the fixture generator requires (2 * (teams - 1)) — the invariant
 * that otherwise throws at seed/fixture time (LeagueFixtureGenerator).
 *
 * Mirrors the seeder's skip rules: team pools (EUR/INT) are validated as
 * per-team files, bare promotion playoffs (ESP3PO) are schedule-only.
 *
 * Exits non-zero if any error is found so it can gate a release pipeline.
 */
class ValidateSeason extends Command
{
    protected $signature = 'app:validate-season
                            {season : Season to validate (e.g. 2026)}';

    protected $description = 'Validate a season data folder for completeness and correctness before seeding';

    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $warnings = [];

    public function handle(CountryConfig $countryConfig): int
    {
        $season = $this->argument('season');
        $base = base_path("data/{$season}");

        if (!is_dir($base)) {
            $this->error("Season folder not found: {$base}");
            return self::FAILURE;
        }

        $this->info("Validating data/{$season}...");
        $this->newLine();

        foreach (SeasonData::competitions($countryConfig) as ['code' => $code, 'type' => $type]) {
            $dir = "{$base}/{$code}";

            match ($type) {
                'league' => $this->validateLeague($code, $dir, $season),
                'cup', 'continental' => $this->validateParticipantList($code, $dir, $season),
                'pool' => $this->validatePool($code, $dir),
                'none' => $this->validateScheduleOnly($code, $dir),
            };
        }

        foreach ($this->warnings as $warning) {
            $this->warn("  ⚠ {$warning}");
        }

        $this->newLine();

        if (!empty($this->errors)) {
            $this->error('Validation FAILED:');
            foreach ($this->errors as $error) {
                $this->line("  ✗ {$error}");
            }
            return self::FAILURE;
        }

        $this->info('Validation passed. Season data is ready to seed.');
        return self::SUCCESS;
    }

    private function validateLeague(string $code, string $dir, string $season): void
    {
        $clubs = $this->loadClubs($code, "{$dir}/teams.json", $season);
        if ($clubs === null) {
            return;
        }

        $teamCount = count($clubs);
        if ($teamCount < 4 || $teamCount % 2 !== 0) {
            $this->errors[] = "{$code}: round-robin league needs an even count ≥ 4, got {$teamCount} clubs.";
        }

        // The fixture generator requires exactly 2*(teams-1) league rounds.
        $schedule = $this->loadSchedule($code, "{$dir}/schedule.json");
        if ($schedule !== null) {
            $rounds = count($schedule['league'] ?? []);
            $expected = 2 * ($teamCount - 1);
            if ($teamCount % 2 === 0 && $rounds !== $expected) {
                $this->errors[] = "{$code}: expected {$expected} league rounds for {$teamCount} teams, schedule has {$rounds}.";
            }
        }

        $this->line("  {$code}: {$teamCount} clubs ✓");
    }

    private function validateParticipantList(string $code, string $dir, string $season): void
    {
        $clubs = $this->loadClubs($code, "{$dir}/teams.json", $season);
        if ($clubs === null) {
            return;
        }
        // Swiss/cup fixtures are drawn per-game, so no round-count invariant.
        if (!file_exists("{$dir}/schedule.json")) {
            $this->warnings[] = "{$code}: no schedule.json (knockout/round dates) found.";
        }
        $this->line("  {$code}: " . count($clubs) . " clubs ✓");
    }

    private function validatePool(string $code, string $dir): void
    {
        $files = array_filter(glob("{$dir}/*.json") ?: [], fn ($p) => basename($p) !== 'schedule.json');
        if (count($files) === 0) {
            $this->errors[] = "{$code}: team pool has no per-team {id}.json files.";
            return;
        }
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data) || empty($data['image']) || SeasonData::idFromImage($data['image']) === null) {
                $this->errors[] = "{$code}: " . basename($file) . " has no resolvable transfermarkt id (image).";
            }
        }
        $this->line("  {$code}: " . count($files) . " pool teams ✓");
    }

    private function validateScheduleOnly(string $code, string $dir): void
    {
        if (!file_exists("{$dir}/schedule.json")) {
            $this->warnings[] = "{$code}: bare playoff has no schedule.json.";
            return;
        }
        $this->loadSchedule($code, "{$dir}/schedule.json");
        $this->line("  {$code}: schedule only ✓");
    }

    /**
     * Load and validate a teams.json clubs array. Returns null (and records an
     * error) when the file is missing or unusable.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function loadClubs(string $code, string $path, string $season): ?array
    {
        if (!file_exists($path)) {
            $this->errors[] = "{$code}: teams.json missing at {$path}.";
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = "{$code}: invalid JSON in teams.json — " . json_last_error_msg();
            return null;
        }

        if (isset($data['seasonID']) && (string) $data['seasonID'] !== $season) {
            $this->errors[] = "{$code}: seasonID is '{$data['seasonID']}', expected '{$season}'.";
        }

        $clubs = $data['clubs'] ?? [];
        if (!is_array($clubs) || count($clubs) === 0) {
            $this->errors[] = "{$code}: teams.json has no clubs.";
            return null;
        }

        foreach ($clubs as $club) {
            if (SeasonData::resolveTransfermarktId($club) === null) {
                $name = $club['name'] ?? '(unnamed)';
                $this->errors[] = "{$code}: club '{$name}' has no resolvable transfermarkt id.";
            }
        }

        return $clubs;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadSchedule(string $code, string $path): ?array
    {
        if (!file_exists($path)) {
            $this->warnings[] = "{$code}: schedule.json missing at {$path}.";
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = "{$code}: invalid JSON in schedule.json — " . json_last_error_msg();
            return null;
        }
        return $data;
    }
}
