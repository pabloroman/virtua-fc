<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Build a Transfermarkt-id → EA Sports FC26-id map by fuzzy name + team
 * matching, into data/{season}/fc26_ids.json (consumed by
 * GamePlayerTemplateService, mirroring data/{season}/sofascore_ids.json).
 *
 * The FC26 export (data/{season}/EAFC26-Men-selected-columns.csv) carries only
 * name + team — no Transfermarkt id — so there is no clean id crosswalk. We
 * therefore:
 *   1. Resolve each real-world club to an FC26 team (per-league one-to-one
 *      assignment for the 6 leagues FC26 shares with us; global fuzzy match for
 *      the European/international pools).
 *   2. Match each player by name *within that resolved FC26 team only*, so a
 *      wrong club mapping yields misses, not wrong ids.
 *
 * The matching is precision-first: unmatched players keep a null fc26_id.
 */
class BuildFc26IdMap extends Command
{
    protected $signature = 'app:build-fc26-id-map
                            {--season= : Season year (defaults to config season.current)}
                            {--report : Print unmatched clubs/players to help tune the alias dict}';

    protected $description = 'Build a Transfermarkt-id → FC26-id map by fuzzy name+team matching into data/{season}/fc26_ids.json';

    /** Player-name acceptance threshold (base score, before nationality tie-break). */
    private const PLAYER_THRESHOLD = 0.82;

    /** Minimum team score to accept a club→FC26-team mapping. */
    private const TEAM_FLOOR_LEAGUE = 0.40;

    private const TEAM_FLOOR_GLOBAL = 0.72;

    /**
     * A correctly-matched club links 15–28 players; a spurious global team match
     * links 1–2 coincidental names. Discard pool clubs below this to stay
     * precision-first. Not applied to the six one-to-one-assigned leagues.
     */
    private const MIN_GLOBAL_LINKS = 5;

    /**
     * Real-world squad folder → FC26 league label. These six leagues line up
     * one-to-one (same clubs), so we resolve their teams by optimal assignment.
     */
    private const LEAGUE_MAP = [
        'ESP1' => 'LALIGA EA SPORTS',
        'ESP2' => 'LALIGA HYPERMOTION',
        'ENG1' => 'Premier League',
        'DEU1' => 'Bundesliga',
        'ITA1' => 'Serie A Enilive',
        'FRA1' => "Ligue 1 McDonald's",
    ];

    /** Per-club pool folders (one JSON file per club). Global team matching. */
    private const POOL_FOLDERS = ['EUR', 'INT'];

    /**
     * Normalised squad team → normalised FC26 team, for pairs pure fuzzy scoring
     * gets wrong or dangerously wrong. The Italian entries are mandatory: FC26
     * uses unlicensed names that would otherwise swap the two Milan / the two
     * Rome-adjacent squads (verified against star rosters). Keys/values are the
     * output of normalizeTeam().
     */
    private const TEAM_ALIASES = [
        // Italy — FC26 unlicensed renames (must not swap rosters)
        'inter milan' => 'lombardia',
        'milan' => 'milano',
        'atalanta' => 'bergamo',
        'lazio' => 'latium',
        // France — abbreviations with no token overlap
        'olympique marseille' => 'om',
        'olympique lyon' => 'ol',
        'paris saint germain' => 'paris sg',
        // England — abbreviations with no token overlap
        'tottenham hotspur' => 'spurs',
        'wolverhampton wanderers' => 'wolves',
        // Germany
        'borussia monchengladbach' => 'm gladbach',
    ];

    /** Tokens that carry no discriminative signal in club names. */
    private const TEAM_STOPWORDS = [
        'fc', 'cf', 'sc', 'ac', 'as', 'aj', 'rc', 'cd', 'sd', 'ud', 'ca', 'rcd',
        'club', 'calcio', 'de', 'of', 'the', 'losc', 'ogc', 'rb', 'sv', 'vfb',
        'vfl', 'tsg', 'fsv', 'bc', 'cfc', 'acf', 'ss', 'ssc', 'us', 'afc', 'bp',
        'ad', 'sk', 'fk',
    ];

    /** Club-name token expansions applied before stopword removal. */
    private const TEAM_TOKEN_EXPAND = ['utd' => 'united'];

    /** Player-name token expansions ("Vini Jr." → "Vini Junior"). */
    private const PLAYER_TOKEN_EXPAND = ['jr' => 'junior'];

    /** FC26 nation label → squad nationality label, for the +bonus tie-break. */
    private const NATION_ALIASES = [
        'holland' => 'netherlands',
        'korea republic' => 'south korea',
        'china pr' => 'china',
        'republic of ireland' => 'ireland',
        'ir iran' => 'iran',
        'ivory coast' => "cote d'ivoire",
        'czechia' => 'czech republic',
    ];

    public function handle(): int
    {
        $season = $this->option('season') ?: config('season.current');

        $csvPath = base_path("data/{$season}/EAFC26-Men-selected-columns.csv");
        if (!file_exists($csvPath)) {
            $this->error("FC26 export not found: {$csvPath}");

            return CommandAlias::FAILURE;
        }

        // FC26 rows indexed by league→team and a flat list of all teams.
        [$fcByLeagueTeam, $fcAllTeams] = $this->loadFc26($csvPath);

        $map = [];                  // transfermarkt_id => fc26_id
        $usedFc26 = [];             // fc26_id already assigned (global uniqueness)
        $seenTm = [];               // transfermarkt_id already processed (dedup)
        $unmatchedClubs = [];       // [folder, clubName] with no FC26 team
        $stats = ['players' => 0, 'clubsMatched' => 0, 'clubsTotal' => 0, 'matched' => 0];

        // --- Pass 1: the six shared leagues, resolved by one-to-one assignment.
        foreach (self::LEAGUE_MAP as $folder => $fcLeague) {
            $clubs = $this->loadTeamsJson(base_path("data/{$season}/{$folder}/teams.json"));
            if ($clubs === null) {
                $this->warn("Skipping {$folder}: teams.json not found.");
                continue;
            }

            $fcTeams = array_keys($fcByLeagueTeam[$fcLeague] ?? []);
            $clubToFcTeam = $this->assignClubsToTeams($clubs, $fcTeams, self::TEAM_FLOOR_LEAGUE);

            foreach ($clubs as $i => $club) {
                $stats['clubsTotal']++;
                $fcTeam = $clubToFcTeam[$i] ?? null;
                if ($fcTeam === null) {
                    $unmatchedClubs[] = [$folder, $club['name']];
                    $this->markSeen($club['players'], $seenTm);
                    continue;
                }
                // One-to-one league assignment is trustworthy: commit always.
                $stats['clubsMatched']++;
                $assignments = $this->matchClubPlayers(
                    $club, $fcByLeagueTeam[$fcLeague][$fcTeam], $usedFc26, $seenTm, $stats
                );
                $this->commit($assignments, $map, $usedFc26, $stats);
            }
        }

        // --- Pass 2: the European / international pools, global team matching.
        foreach (self::POOL_FOLDERS as $folder) {
            foreach ($this->loadPoolClubs(base_path("data/{$season}/{$folder}")) as $club) {
                $stats['clubsTotal']++;
                [$fcTeam, $roster] = $this->bestGlobalTeam($club['name'], $fcAllTeams);
                if ($fcTeam === null) {
                    $unmatchedClubs[] = [$folder, $club['name']];
                    $this->markSeen($club['players'], $seenTm);
                    continue;
                }
                // Global matches can be spurious — only commit if the team links
                // enough players to look like a real roster match.
                $assignments = $this->matchClubPlayers($club, $roster, $usedFc26, $seenTm, $stats);
                if (count($assignments) >= self::MIN_GLOBAL_LINKS) {
                    $stats['clubsMatched']++;
                    $this->commit($assignments, $map, $usedFc26, $stats);
                } else {
                    $unmatchedClubs[] = [$folder, $club['name']];
                }
            }
        }

        ksort($map, SORT_STRING);

        $outputPath = base_path("data/{$season}/fc26_ids.json");
        file_put_contents(
            $outputPath,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $this->info("Wrote {$outputPath}");
        $this->line('  Players enumerated:  ' . number_format($stats['players']));
        $this->line('  Clubs matched:       ' . $stats['clubsMatched'] . ' / ' . $stats['clubsTotal']);
        $pct = $stats['players'] > 0 ? round(100 * $stats['matched'] / $stats['players'], 1) : 0;
        $this->line('  Players linked:      ' . number_format($stats['matched']) . " ({$pct}%)");

        if ($this->option('report')) {
            $this->reportUnmatched($unmatchedClubs);
        }

        return CommandAlias::SUCCESS;
    }

    /**
     * Propose (transfermarkt_id → fc26_id) links for a club's not-yet-seen
     * players against a resolved FC26 roster, via greedy one-to-one assignment
     * on name score (≥ threshold), nationality used only as a tie-break. Marks
     * players seen and counts them ($seenTm / $stats['players']); does NOT commit
     * — the caller decides whether the proposal is trustworthy.
     *
     * @param  array{name: string, players: list<array>}  $club
     * @param  list<array{id: string, name: string, nation: string}>  $roster
     * @param  array<string, true>  $usedFc26
     * @return list<array{0: string, 1: string}>  [transfermarkt_id, fc26_id] pairs
     */
    private function matchClubPlayers(array $club, array $roster, array $usedFc26, array &$seenTm, array &$stats): array
    {
        // Pre-tokenise the FC26 roster once.
        $fcPlayers = [];
        foreach ($roster as $r) {
            if (isset($usedFc26[$r['id']])) {
                continue;
            }
            $fcPlayers[] = [
                'id' => $r['id'],
                'tokens' => $this->normalizePlayer($r['name']),
                'nation' => $this->normalizeNation($r['nation']),
            ];
        }

        // Score every (squad player, fc26 player) pair above the threshold.
        $candidates = [];
        foreach ($club['players'] as $p) {
            $tm = (string) ($p['id'] ?? '');
            if ($tm === '' || isset($seenTm[$tm])) {
                continue;
            }
            $seenTm[$tm] = true;
            $stats['players']++;

            $tokens = $this->normalizePlayer($p['name'] ?? '');
            $nats = array_map(fn ($n) => Str::lower($n), $p['nationality'] ?? []);

            foreach ($fcPlayers as $fp) {
                $base = $this->scoreTokens($tokens, $fp['tokens']);
                if ($base < self::PLAYER_THRESHOLD) {
                    continue;
                }
                $bonus = in_array($fp['nation'], $nats, true) ? 0.05 : 0.0;
                $candidates[] = [$tm, $fp['id'], $base + $bonus];
            }
        }

        // Greedy one-to-one within the club: highest-scoring pairs claim first.
        usort($candidates, fn ($a, $b) => $b[2] <=> $a[2]);
        $assignments = [];
        $usedTm = [];
        $usedFc = [];
        foreach ($candidates as [$tm, $fcId]) {
            if (isset($usedTm[$tm]) || isset($usedFc[$fcId])) {
                continue;
            }
            $usedTm[$tm] = true;
            $usedFc[$fcId] = true;
            $assignments[] = [$tm, $fcId];
        }

        return $assignments;
    }

    /**
     * Commit proposed links into the global map, skipping any fc26_id already
     * taken by another club (global uniqueness).
     *
     * @param  list<array{0: string, 1: string}>  $assignments
     */
    private function commit(array $assignments, array &$map, array &$usedFc26, array &$stats): void
    {
        foreach ($assignments as [$tm, $fcId]) {
            if (isset($map[$tm]) || isset($usedFc26[$fcId])) {
                continue;
            }
            $map[$tm] = $fcId;
            $usedFc26[$fcId] = true;
            $stats['matched']++;
        }
    }

    /**
     * Greedy optimal assignment of squad clubs to FC26 team names (each used
     * once). Returns [squadClubIndex => fcTeamName]; clubs below the floor or
     * with no team left are absent.
     *
     * @param  list<array{name: string}>  $clubs
     * @param  list<string>  $fcTeams
     * @return array<int, string>
     */
    private function assignClubsToTeams(array $clubs, array $fcTeams, float $floor): array
    {
        $fcNorm = [];
        foreach ($fcTeams as $t) {
            $fcNorm[$t] = $this->tokens($this->normalizeTeam($t));
        }

        $candidates = [];
        foreach ($clubs as $i => $club) {
            $squadNorm = $this->normalizeTeam($club['name']);
            $squadNorm = self::TEAM_ALIASES[$squadNorm] ?? $squadNorm;
            $squadTokens = $this->tokens($squadNorm);
            foreach ($fcTeams as $t) {
                $score = $this->scoreTokens($squadTokens, $fcNorm[$t]);
                if ($score >= $floor) {
                    $candidates[] = [$i, $t, $score];
                }
            }
        }

        usort($candidates, fn ($a, $b) => $b[2] <=> $a[2]);
        $result = [];
        $usedTeam = [];
        foreach ($candidates as [$i, $t, $score]) {
            if (isset($result[$i]) || isset($usedTeam[$t])) {
                continue;
            }
            $result[$i] = $t;
            $usedTeam[$t] = true;
        }

        return $result;
    }

    /**
     * Best FC26 team for a club name across all leagues (≥ global floor).
     *
     * @param  array<string, list<array{id: string, name: string, nation: string}>>  $fcAllTeams
     * @return array{0: ?string, 1: list<array>}
     */
    private function bestGlobalTeam(string $clubName, array $fcAllTeams): array
    {
        $squadNorm = $this->normalizeTeam($clubName);
        $squadNorm = self::TEAM_ALIASES[$squadNorm] ?? $squadNorm;
        $squadTokens = $this->tokens($squadNorm);

        $best = null;
        $bestScore = self::TEAM_FLOOR_GLOBAL;
        foreach ($fcAllTeams as $team => $roster) {
            $score = $this->scoreTokens($squadTokens, $this->tokens($this->normalizeTeam($team)));
            if ($score >= $bestScore) {
                $bestScore = $score;
                $best = $team;
            }
        }

        return $best === null ? [null, []] : [$best, $fcAllTeams[$best]];
    }

    /**
     * Token-similarity score in [0, 1]. Combines full-string ratio, token-set
     * Dice, subset containment, prefix alignment ("vini" ↔ "vinicius") and a
     * surname match.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function scoreTokens(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $full = 0.0;
        similar_text(implode(' ', $a), implode(' ', $b), $full);
        $full /= 100;

        $setA = array_values(array_unique($a));
        $setB = array_values(array_unique($b));
        $inter = count(array_intersect($setA, $setB));
        $dice = 2 * $inter / (count($setA) + count($setB));

        $subset = (array_diff($setA, $setB) === [] || array_diff($setB, $setA) === []) ? 1.0 : 0.0;

        $short = count($setA) <= count($setB) ? $setA : $setB;
        $long = count($setA) <= count($setB) ? $setB : $setA;
        $considered = 0;
        $aligned = 0;
        foreach ($short as $t) {
            if (strlen($t) < 3) {
                continue;
            }
            $considered++;
            foreach ($long as $u) {
                // Both sides must be ≥3 chars: a 1–2 letter token (e.g. "c" in
                // "D.C. United") would otherwise prefix-match any word starting
                // with that letter ("copenhagen"), producing phantom matches.
                if (strlen($u) < 3) {
                    continue;
                }
                if (str_starts_with($u, $t) || str_starts_with($t, $u)) {
                    $aligned++;
                    break;
                }
            }
        }
        $prefix = $considered > 0 ? $aligned / $considered : 0.0;

        $lastA = end($a);
        $lastB = end($b);
        $surname = ($lastA === $lastB && strlen($lastA) > 3) ? 1.0 : 0.0;

        return max($full, $dice, 0.92 * $subset, 0.88 * $prefix, 0.80 * $surname);
    }

    /** Normalize a club name to a token string (accents, case, stopwords). */
    private function normalizeTeam(string $name): string
    {
        $tokens = $this->tokens($this->asciiLower($name));
        $expanded = [];
        foreach ($tokens as $t) {
            $t = self::TEAM_TOKEN_EXPAND[$t] ?? $t;
            if (!in_array($t, self::TEAM_STOPWORDS, true)) {
                $expanded[] = $t;
            }
        }

        // Don't let stopword stripping empty the name (e.g. an all-affix token).
        return implode(' ', $expanded !== [] ? $expanded : $tokens);
    }

    /**
     * Normalize a player name to a token list.
     *
     * @return list<string>
     */
    private function normalizePlayer(string $name): array
    {
        return array_map(
            fn ($t) => self::PLAYER_TOKEN_EXPAND[$t] ?? $t,
            $this->tokens($this->asciiLower($name)),
        );
    }

    private function normalizeNation(string $nation): string
    {
        $n = Str::lower(trim($nation));

        return self::NATION_ALIASES[$n] ?? $n;
    }

    private function asciiLower(string $s): string
    {
        return Str::lower(Str::ascii($s));
    }

    /**
     * Split a normalized string into alnum tokens.
     *
     * @return list<string>
     */
    private function tokens(string $s): array
    {
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);

        return array_values(array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($s)))));
    }

    /**
     * Load the FC26 export. Returns [ league => [team => rosterRows], allTeams ].
     *
     * @return array{0: array<string, array<string, list<array>>>, 1: array<string, list<array>>}
     */
    private function loadFc26(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $idx = array_flip($header);

        $byLeagueTeam = [];
        $allTeams = [];
        while (($row = fgetcsv($handle)) !== false) {
            $league = $row[$idx['League']] ?? '';
            $team = $row[$idx['Team']] ?? '';
            $entry = [
                'id' => trim((string) ($row[$idx['ID']] ?? '')),
                'name' => $row[$idx['Name']] ?? '',
                'nation' => $row[$idx['Nation']] ?? '',
            ];
            if ($entry['id'] === '' || $team === '') {
                continue;
            }
            $byLeagueTeam[$league][$team][] = $entry;
            $allTeams[$team][] = $entry;
        }
        fclose($handle);

        return [$byLeagueTeam, $allTeams];
    }

    /**
     * Load a competition teams.json as a list of clubs.
     *
     * @return list<array{name: string, players: list<array>}>|null
     */
    private function loadTeamsJson(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);

        return array_map(
            fn ($club) => ['name' => $club['name'] ?? '', 'players' => $club['players'] ?? []],
            $data['clubs'] ?? [],
        );
    }

    /**
     * Load a pool folder's per-club JSON files as a list of clubs.
     *
     * @return list<array{name: string, players: list<array>}>
     */
    private function loadPoolClubs(string $dir): array
    {
        $clubs = [];
        foreach (glob("{$dir}/*.json") ?: [] as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data) || !isset($data['players'])) {
                continue;
            }
            $clubs[] = ['name' => $data['name'] ?? '', 'players' => $data['players']];
        }

        return $clubs;
    }

    /**
     * Mark a club's players as seen without attempting a match (unmatched club).
     *
     * @param  list<array>  $players
     */
    private function markSeen(array $players, array &$seenTm): void
    {
        foreach ($players as $p) {
            $tm = (string) ($p['id'] ?? '');
            if ($tm !== '') {
                $seenTm[$tm] = true;
            }
        }
    }

    /** @param  list<array{0: string, 1: string}>  $unmatchedClubs */
    private function reportUnmatched(array $unmatchedClubs): void
    {
        $this->newLine();
        $this->line('Unmatched clubs (no FC26 team — players left null):');
        if ($unmatchedClubs === []) {
            $this->line('  (none)');

            return;
        }
        foreach ($unmatchedClubs as [$folder, $name]) {
            $this->line("  [{$folder}] {$name}");
        }
    }
}
