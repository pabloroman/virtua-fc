<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\SwissDrawService;
use App\Support\SeasonData;
use Database\Seeders\ClubProfilesSeeder;
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
 * Continental competitions (UCL/UEL/UECL/UEFASUP) get extra rules, because
 * their participant lists only *link* clubs seeded elsewhere. A participant
 * with no squad data anywhere in the season folder is dropped with a warning by
 * SeedReferenceData, and SeasonInitializationService then finds an incomplete
 * league phase and skips it — leaving a competition with no fixtures at all and
 * nothing user-visible to explain why. Catching that here is the whole point.
 * Their shape is checked too: a Swiss league phase is exactly 36 clubs and no
 * club appears in two of them, a continental knockout is exactly two.
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

    /**
     * Transfermarkt id -> the Swiss competition that already claimed it. A club
     * plays in exactly one of UCL/UEL/UECL, so a second claim is a data error.
     * The Super Cup is deliberately absent: its two finalists legitimately also
     * play in that season's Champions or Europa League.
     *
     * @var array<string, string>
     */
    private array $swissEntrants = [];

    /**
     * Every transfermarkt id in this season folder that can supply a squad —
     * league teams.json clubs and EUR/INT pool files — mapped to its name and
     * country. Continental competitions only *link* clubs, so this is what
     * their participants have to resolve against.
     *
     * @var array<string, array{name: string, country: string|null}>
     */
    private array $squadSources = [];

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

        $competitions = SeasonData::competitions($countryConfig);
        $handlers = SeasonData::continentalHandlers($countryConfig);
        $this->indexSquadSources($season, $competitions, $countryConfig);

        foreach ($competitions as ['code' => $code, 'type' => $type]) {
            $dir = "{$base}/{$code}";

            match ($type) {
                'league' => $this->validateLeague($code, $dir, $season),
                'cup' => $this->validateParticipantList($code, $dir, $season),
                'continental' => $this->validateContinental($code, $dir, $season, $handlers[$code] ?? ''),
                'pool' => $this->validatePool($code, $dir),
                'none' => $this->validateScheduleOnly($code, $dir),
            };
        }

        $this->warnUnprofiledClubs();

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
        $this->validateEntryRounds($code, $dir, $clubs);
        $this->line("  {$code}: " . count($clubs) . " clubs ✓");
    }

    /**
     * A club's `entryRound` has to be a round the cup actually has.
     *
     * @param  array<int, array<string, mixed>>  $clubs
     */
    private function validateEntryRounds(string $code, string $dir, array $clubs): void
    {
        $schedule = file_exists("{$dir}/schedule.json")
            ? json_decode((string) file_get_contents("{$dir}/schedule.json"), true)
            : null;
        $rounds = array_map(fn ($round) => (int) ($round['round'] ?? 0), $schedule['knockout'] ?? []);
        $lastRound = $rounds === [] ? null : max($rounds);

        foreach ($clubs as $club) {
            if (!isset($club['entryRound'])) {
                continue;
            }
            $entryRound = (int) $club['entryRound'];
            $name = $club['name'] ?? '(unnamed)';
            if ($entryRound < 1 || ($lastRound !== null && $entryRound > $lastRound)) {
                $this->errors[] = "{$code}: club '{$name}' has entryRound {$entryRound}, outside rounds 1–" . ($lastRound ?? '?') . '.';
            }
        }
    }

    /**
     * Continental participant lists get every check a cup gets, plus the ones
     * that keep a European competition from quietly ending up empty.
     */
    private function validateContinental(string $code, string $dir, string $season, string $handler): void
    {
        $clubs = $this->loadClubs($code, "{$dir}/teams.json", $season);
        if ($clubs === null) {
            return;
        }

        if (!file_exists("{$dir}/schedule.json")) {
            $this->warnings[] = "{$code}: no schedule.json (knockout/round dates) found.";
        }

        $errorsBefore = count($this->errors);

        $this->validateParticipantsAreSeedable($code, $season, $clubs);

        if ($handler === 'swiss_format') {
            $this->validateSwissShape($code, $clubs);
            $this->validateNoRepeatEntrants($code, $clubs);
        }

        // A continental knockout is the one-off Super Cup: prior UCL winner v
        // prior UEL winner, a single tie. On the initial season those two rows
        // *are* the finalists (UefaSuperCupQualificationProcessor leaves seed
        // data alone), so any other count produces a draw that cannot be made.
        if ($handler === 'knockout_cup') {
            $total = count($clubs);
            if ($total !== 2) {
                $this->errors[] = "{$code}: a continental knockout is a single two-club tie, got {$total} clubs.";
            }
        }

        if (count($this->errors) === $errorsBefore) {
            $this->line("  {$code}: " . count($clubs) . " clubs ✓");
        }
    }

    /**
     * A club plays in exactly one of the Swiss competitions. Two claims mean the
     * participant lists were read off a page that spans more than one — a
     * fixture list including qualifying rounds puts a club knocked out of the
     * Champions League into both it and the Europa League.
     *
     * @param  array<int, array<string, mixed>>  $clubs
     */
    private function validateNoRepeatEntrants(string $code, array $clubs): void
    {
        foreach ($clubs as $club) {
            if (empty($club['id'])) {
                continue;   // already reported by validateParticipantsAreSeedable
            }

            $id = (string) $club['id'];
            $name = $club['name'] ?? '(unnamed)';

            if (isset($this->swissEntrants[$id])) {
                $this->errors[] = "{$code}: '{$name}' ({$id}) is also a {$this->swissEntrants[$id]} "
                    . 'entrant — a club plays in one European competition per season.';
                continue;
            }

            $this->swissEntrants[$id] = $code;
        }
    }

    /**
     * Every participant must carry a literal `id` and have squad data somewhere
     * in this season folder.
     *
     * The literal-`id` rule is deliberately stricter than loadClubs(): the
     * seeder and SetupNewGame both read `$club['id']` directly, so a club
     * identified only by `transfermarktId` or a crest URL resolves here but is
     * skipped there. Without squad data the seeder drops the club with a
     * warning and the competition is left short of a full league phase, which
     * SeasonInitializationService then skips outright — no fixtures, no error.
     *
     * @param  array<int, array<string, mixed>>  $clubs
     */
    private function validateParticipantsAreSeedable(string $code, string $season, array $clubs): void
    {
        $unseedable = [];

        foreach ($clubs as $club) {
            $name = $club['name'] ?? '(unnamed)';

            if (empty($club['id'])) {
                $this->errors[] = "{$code}: club '{$name}' has no literal 'id' key — "
                    . 'the seeder links continental clubs by that field only.';
                continue;
            }

            $id = (string) $club['id'];
            if (!isset($this->squadSources[$id])) {
                $unseedable[] = "{$name} ({$id})";
            }
        }

        if (!empty($unseedable)) {
            $this->errors[] = "{$code}: " . count($unseedable) . ' club(s) have no squad data — not in any '
                . "league teams.json and no data/{$season}/{EUR,INT}/{id}.json: " . implode(', ', $unseedable) . '.';
        }
    }

    /**
     * A Swiss league phase is drawn from four equal pots, so the participant
     * list has to be exactly that shape or SwissDrawService throws.
     *
     * Real seeding pots are optional — the scraper cannot read them off
     * Transfermarkt, and SetupNewGame falls back to pots by squad market value
     * when they are absent. A *partial* set is the dangerous case: it usually
     * means a re-scrape overwrote hand-entered pots.
     *
     * @param  array<int, array<string, mixed>>  $clubs
     */
    private function validateSwissShape(string $code, array $clubs): void
    {
        $total = count($clubs);
        $expected = SwissDrawService::LEAGUE_PHASE_TEAMS;

        if ($total !== $expected) {
            $this->errors[] = "{$code}: swiss league phase needs exactly {$expected} clubs, got {$total}.";
        }

        $potted = array_values(array_filter($clubs, fn ($club) => isset($club['pot'])));

        if (count($potted) === 0) {
            $this->warnings[] = "{$code}: no seeding pots — the draw will fall back to squad market value.";
        } elseif (count($potted) !== $total) {
            $this->errors[] = "{$code}: only " . count($potted) . " of {$total} clubs have a 'pot' "
                . '(a re-scrape probably overwrote them). Give every club a pot, or none at all.';
        } else {
            $sizes = array_count_values(array_map(fn ($club) => (int) $club['pot'], $potted));
            $perPot = SwissDrawService::TEAMS_PER_POT;
            $wrong = [];

            foreach (range(1, SwissDrawService::POTS) as $pot) {
                if (($sizes[$pot] ?? 0) !== $perPot) {
                    $wrong[] = "pot {$pot} has " . ($sizes[$pot] ?? 0);
                }
            }
            foreach (array_keys($sizes) as $pot) {
                if ($pot < 1 || $pot > SwissDrawService::POTS) {
                    $wrong[] = "pot {$pot} is out of range";
                }
            }

            if (!empty($wrong)) {
                $this->errors[] = "{$code}: every pot must hold exactly {$perPot} clubs — "
                    . implode(', ', $wrong) . '.';
            }
        }

        $noCountry = array_values(array_filter(
            $clubs,
            fn ($club) => empty($club['country'])
                && empty($this->squadSources[(string) ($club['id'] ?? '')]['country']),
        ));

        if (!empty($noCountry)) {
            $names = array_map(fn ($club) => (string) ($club['name'] ?? '(unnamed)'), $noCountry);
            $this->warnings[] = "{$code}: " . count($noCountry) . ' club(s) have no country, so the draw '
                . 'cannot keep them apart from their compatriots: ' . $this->summarize($names) . '.';
        }
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
     * Index every club in this season folder that can supply a squad: clubs in
     * a league teams.json (country from config/countries.php) and EUR/INT pool
     * files (country from the file itself).
     *
     * Built once per run and read by the continental checks. Missing files are
     * skipped silently — they are reported by their own competition's checks,
     * and a partial folder must not fatal here.
     *
     * @param  array<int, array{code: string, type: string}>  $competitions
     */
    private function indexSquadSources(string $season, array $competitions, CountryConfig $countryConfig): void
    {
        foreach ($competitions as ['code' => $code, 'type' => $type]) {
            if ($type === 'league') {
                $country = $countryConfig->countryForCompetition($code);
                foreach (SeasonData::readCompetitionClubs($season, $code, $type) ?? [] as $club) {
                    $this->squadSources[$club['id']] ??= ['name' => $club['name'], 'country' => $country];
                }
                continue;
            }

            if ($type === 'pool') {
                // Read the pool files directly rather than via
                // readCompetitionClubs: only the file itself carries the club's
                // country, and it is the country that makes the Swiss draw able
                // to keep compatriots apart.
                $dir = base_path("data/{$season}/{$code}");
                $files = array_filter(glob("{$dir}/*.json") ?: [], fn ($p) => basename($p) !== 'schedule.json');

                foreach ($files as $file) {
                    $data = json_decode((string) file_get_contents($file), true);
                    if (!is_array($data)) {
                        continue;
                    }
                    $id = SeasonData::resolveTransfermarktId($data);
                    if ($id === null) {
                        continue;
                    }
                    $this->squadSources[$id] ??= [
                        'name' => (string) ($data['name'] ?? "({$id})"),
                        'country' => !empty($data['country']) ? (string) $data['country'] : null,
                    ];
                }
            }
        }
    }

    /**
     * Flag clubs with no curated entry in ClubProfilesSeeder, which silently
     * fall back to a local-reputation, neutral-loyalty profile — wrong for a
     * side that just qualified for Europe.
     *
     * Kept to a single summary line (the full list needs -v): the current data
     * has dozens of these and a wall of warnings just trains people to ignore
     * the validator. Names are taken from the squad-source index rather than
     * the continental lists, because those use short forms ("Barcelona") that
     * never match the seeded team name ("FC Barcelona").
     */
    private function warnUnprofiledClubs(): void
    {
        $profiled = array_flip(ClubProfilesSeeder::profiledClubNames());
        $missing = [];

        foreach ($this->squadSources as $source) {
            if (!isset($profiled[$source['name']])) {
                $missing[] = $source['name'];
            }
        }

        if (empty($missing)) {
            return;
        }

        sort($missing);
        $detail = $this->output->isVerbose()
            ? implode(', ', $missing)
            : 'run with -v to list them';

        $this->warnings[] = count($missing) . ' club(s) have no ClubProfilesSeeder entry and will be seeded '
            . "as local-reputation clubs: {$detail}.";
    }

    /**
     * Render a name list without letting a wholesale gap print dozens of lines.
     *
     * @param  array<int, string>  $names
     */
    private function summarize(array $names, int $limit = 5): string
    {
        if (count($names) <= $limit) {
            return implode(', ', $names);
        }

        return implode(', ', array_slice($names, 0, $limit)) . ' and ' . (count($names) - $limit) . ' more';
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
