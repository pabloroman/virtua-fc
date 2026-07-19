<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\SeasonData;
use Illuminate\Console\Command;

/**
 * Report squad changes between two season data folders — the human-review
 * artifact for a refresh PR.
 *
 * For every competition it compares the target season against `--from`
 * (defaults to the previous year) and lists, per club: players in (signings),
 * players out (departures), and clubs that joined or left the competition
 * (promotion/relegation). Players and clubs are matched by transfermarkt id.
 *
 * `--format=md` emits a collapsible markdown summary suitable for posting as a
 * PR comment from the season-data CI workflow; the default `text` format is for
 * the terminal.
 */
class DiffSeason extends Command
{
    protected $signature = 'app:diff-season
                            {season : Season to inspect (e.g. 2026)}
                            {--from= : Season to compare against (defaults to season - 1)}
                            {--format=text : Output format: text or md}';

    protected $description = 'Report squad changes (signings, departures, club movements) between two season data folders';

    public function handle(CountryConfig $countryConfig): int
    {
        $season = $this->argument('season');
        $from = $this->option('from') ?: (string) ((int) $season - 1);
        $format = $this->option('format');

        if (!is_dir(base_path("data/{$season}"))) {
            $this->error("Season folder not found: data/{$season}");
            return self::FAILURE;
        }
        if (!is_dir(base_path("data/{$from}"))) {
            // No prior season to compare against (e.g. a PR that edits the
            // earliest season folder). There's nothing to diff, so emit a benign
            // note and succeed rather than failing the season-data CI job.
            $this->line($format === 'md'
                ? "**Season data {$season}** — no comparison baseline (`data/{$from}` not found)."
                : "No comparison baseline: data/{$from} not found; nothing to diff.");

            return self::SUCCESS;
        }

        $sections = [];
        foreach (SeasonData::competitions($countryConfig) as ['code' => $code, 'type' => $type]) {
            if ($type === 'none') {
                continue;
            }

            $new = SeasonData::readCompetitionClubs($season, $code, $type);
            $old = SeasonData::readCompetitionClubs($from, $code, $type);
            if ($new === null && $old === null) {
                continue;
            }

            $section = $this->diffCompetition($code, $old ?? [], $new ?? []);
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        $output = $format === 'md'
            ? $this->renderMarkdown($season, $from, $sections)
            : $this->renderText($season, $from, $sections);

        $this->line($output);

        return self::SUCCESS;
    }

    /**
     * Build the change record for one competition, or null when nothing changed.
     *
     * @param  array<int, array{id: string, name: string, players: array<string, string>}>  $old
     * @param  array<int, array{id: string, name: string, players: array<string, string>}>  $new
     * @return array{code: string, clubsIn: array<int, string>, clubsOut: array<int, string>, clubs: array<int, array{name: string, in: array<int, string>, out: array<int, string>}>}|null
     */
    private function diffCompetition(string $code, array $old, array $new): ?array
    {
        $oldById = $this->keyById($old);
        $newById = $this->keyById($new);

        $clubsIn = [];
        $clubsOut = [];
        $clubChanges = [];

        foreach ($newById as $id => $club) {
            if (!isset($oldById[$id])) {
                $clubsIn[] = $club['name'];
            }
        }
        foreach ($oldById as $id => $club) {
            if (!isset($newById[$id])) {
                $clubsOut[] = $club['name'];
            }
        }

        foreach ($newById as $id => $club) {
            if (!isset($oldById[$id])) {
                continue;
            }
            $in = array_values(array_diff_key($club['players'], $oldById[$id]['players']));
            $out = array_values(array_diff_key($oldById[$id]['players'], $club['players']));
            if ($in !== [] || $out !== []) {
                sort($in);
                sort($out);
                $clubChanges[] = ['name' => $club['name'], 'in' => $in, 'out' => $out];
            }
        }

        if ($clubsIn === [] && $clubsOut === [] && $clubChanges === []) {
            return null;
        }

        sort($clubsIn);
        sort($clubsOut);
        usort($clubChanges, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return ['code' => $code, 'clubsIn' => $clubsIn, 'clubsOut' => $clubsOut, 'clubs' => $clubChanges];
    }

    /**
     * @param  array<int, array{id: string, name: string, players: array<string, string>}>  $clubs
     * @return array<string, array{id: string, name: string, players: array<string, string>}>
     */
    private function keyById(array $clubs): array
    {
        $byId = [];
        foreach ($clubs as $club) {
            $byId[$club['id']] = $club;
        }

        return $byId;
    }

    /**
     * @param  array<int, array{code: string, clubsIn: array<int, string>, clubsOut: array<int, string>, clubs: array<int, array{name: string, in: array<int, string>, out: array<int, string>}>}>  $sections
     */
    private function renderText(string $season, string $from, array $sections): string
    {
        if ($sections === []) {
            return "No squad changes between data/{$from} and data/{$season}.";
        }

        $lines = ["Squad changes: data/{$from} → data/{$season}", ''];
        foreach ($sections as $section) {
            $lines[] = "{$section['code']}";
            foreach ($section['clubsIn'] as $name) {
                $lines[] = "  + club: {$name}";
            }
            foreach ($section['clubsOut'] as $name) {
                $lines[] = "  - club: {$name}";
            }
            foreach ($section['clubs'] as $club) {
                $lines[] = "  {$club['name']}";
                foreach ($club['in'] as $player) {
                    $lines[] = "    + {$player}";
                }
                foreach ($club['out'] as $player) {
                    $lines[] = "    - {$player}";
                }
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * @param  array<int, array{code: string, clubsIn: array<int, string>, clubsOut: array<int, string>, clubs: array<int, array{name: string, in: array<int, string>, out: array<int, string>}>}>  $sections
     */
    private function renderMarkdown(string $season, string $from, array $sections): string
    {
        if ($sections === []) {
            return "**Season data {$season}** — no squad changes vs `{$from}`.";
        }

        $signings = array_sum(array_map(fn ($s) => array_sum(array_map(fn ($c) => count($c['in']), $s['clubs'])), $sections));
        $departures = array_sum(array_map(fn ($s) => array_sum(array_map(fn ($c) => count($c['out']), $s['clubs'])), $sections));

        $lines = [
            "### Season data `{$from}` → `{$season}`",
            '',
            "**{$signings}** signings, **{$departures}** departures across " . count($sections) . ' competition(s).',
            '',
        ];

        foreach ($sections as $section) {
            $lines[] = '<details>';
            $lines[] = "<summary><strong>{$section['code']}</strong></summary>";
            $lines[] = '';
            foreach ($section['clubsIn'] as $name) {
                $lines[] = "- 🟢 **Club joined:** {$name}";
            }
            foreach ($section['clubsOut'] as $name) {
                $lines[] = "- 🔴 **Club left:** {$name}";
            }
            foreach ($section['clubs'] as $club) {
                $lines[] = "- **{$club['name']}**";
                foreach ($club['in'] as $player) {
                    $lines[] = "  - 🟢 {$player}";
                }
                foreach ($club['out'] as $player) {
                    $lines[] = "  - 🔴 {$player}";
                }
            }
            $lines[] = '';
            $lines[] = '</details>';
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }
}
