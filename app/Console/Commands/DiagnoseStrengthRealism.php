<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Match\Services\CompetitionStrengthFloorResolver;
use App\Modules\Match\Support\MatchOutcomeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Measure whether squad-strength differences translate into realistic match and
 * season outcomes — i.e. does the better/higher-reputation team reliably prevail
 * over a 38-game season, or has the flattened overall-score distribution turned
 * results into coin flips?
 *
 * Read-only. It computes each team's strength with the SAME definition the match
 * engine uses (mean of the best XI's `overall·0.95 + morale·0.05`, /100) and
 * drives the SAME outcome math (xG power-law + Dixon-Coles) via the shared
 * {@see MatchOutcomeModel}, so the numbers reflect production behaviour, not a
 * re-implementation.
 *
 * Note: the AI-vs-AI league (the tables forming around the user) is resolved by
 * AIMatchResolver, which omits per-player "form on the day" variance — so AI
 * league realism is governed ONLY by strength ratios + skill_dominance +
 * Dixon-Coles. The analytical sections model that exactly. `--std-dev` lets you
 * additionally approximate the user's own MatchSimulator matches, which DO carry
 * per-player variance.
 *
 * The `--floor`, `--skill-dominance` and `--std-dev` flags preview tuning levers
 * without touching config or code, turning this into the calibration instrument
 * for the data-driven mitigation pass (favoured lever: the strength-floor
 * rescale `(rating - floor)/(100 - floor)`, previewed via `--floor`).
 */
class DiagnoseStrengthRealism extends Command
{
    protected $signature = 'app:diagnose-strength-realism
        {game? : Game id (defaults to the most recently created game)}
        {--league= : Competition code, e.g. ESP1 (defaults to the game\'s own league)}
        {--runs=2000 : Number of Monte-Carlo seasons for the distribution section}
        {--floor= : Preview the strength-floor rescale: strength = (rating - floor)/(100 - floor)}
        {--auto-floor : Derive & apply the league floor via the production CompetitionStrengthFloorResolver}
        {--skill-dominance= : Preview a different skill_dominance exponent}
        {--std-dev= : Add per-match team form noise (models user MatchSimulator matches; AI league uses none)}
        {--neutral : Ignore home advantage in every matchup}
        {--json= : Also write the full metric set as JSON to this path}';

    protected $description = 'Measure whether squad-strength differences yield realistic match/season outcomes (read-only).';

    /**
     * Real top-flight reference bands used purely for a ✓/⚠ sanity flag. These
     * are loose realism targets (≈ La Liga over a 20-team, 38-game season), not
     * hard rules — the point is to see whether simulated spread/correlation land
     * in a plausible neighbourhood, not to hit an exact number.
     */
    private const BENCHMARK_CHAMPION_PTS = [82, 95];

    private const BENCHMARK_LAST_PTS = [18, 32];

    private const BENCHMARK_SPREAD = [50, 72];

    private const BENCHMARK_MIN_SPEARMAN = 0.70;

    private bool $neutral = false;

    private ?float $skillDominance = null;

    private ?float $stdDev = null;

    public function handle(): int
    {
        $game = $this->resolveGame();
        if (! $game) {
            return self::FAILURE;
        }

        $league = (string) ($this->option('league') ?: $game->competition_id);
        $this->neutral = (bool) $this->option('neutral');
        $this->skillDominance = $this->option('skill-dominance') !== null
            ? (float) $this->option('skill-dominance') : null;
        $this->stdDev = $this->option('std-dev') !== null
            ? (float) $this->option('std-dev') : null;
        $floor = $this->option('floor') !== null ? (float) $this->option('floor') : null;
        if ($this->option('auto-floor')) {
            // Mirror production: the floor the live engine would use for this league.
            $floor = (new CompetitionStrengthFloorResolver)->leagueFloor($game, $league);
        }
        $runs = max(1, (int) $this->option('runs'));

        $teamIds = CompetitionEntry::query()
            ->where('game_id', $game->id)
            ->where('competition_id', $league)
            ->pluck('team_id')
            ->all();

        if (count($teamIds) < 4) {
            $this->error("League {$league} has only " . count($teamIds) . ' team(s) in game ' . $game->id . ' — need at least 4.');

            return self::FAILURE;
        }

        $names = Team::query()->whereIn('id', $teamIds)->pluck('name', 'id')->all();
        $playersByTeam = GamePlayer::query()
            ->where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->with('matchState')
            ->get(['id', 'game_id', 'team_id', 'overall_score', 'position'])
            ->groupBy('team_id');

        // Per-team rating (0..100 weighted XI mean) and engine strength (0..1).
        $rating = [];
        $strength = [];
        foreach ($teamIds as $tid) {
            $xi = $this->bestEleven($playersByTeam->get($tid, collect()));
            $rating[$tid] = $this->weightedRating($xi);
            $strength[$tid] = $this->toStrength($rating[$tid], $xi->count(), $floor);
        }

        // Rank teams by strength descending → strengthRank[teamId] = 1..n.
        $ranked = $teamIds;
        usort($ranked, fn ($a, $b) => $strength[$b] <=> $strength[$a]);
        $strengthRank = [];
        foreach ($ranked as $idx => $tid) {
            $strengthRank[$tid] = $idx + 1;
        }

        $this->printContext($game, $league, count($teamIds), $floor, $runs);
        $this->sectionInputSpread($ranked, $names, $rating, $strength);

        // Precompute the analytical outcome for every ordered fixture once. With
        // no form noise the matrices are reused for both the expected-points
        // table and the Monte-Carlo sampling, keeping the whole run cheap.
        [$probs, $matrices] = $this->precomputeFixtures($teamIds, $strength);

        $this->sectionPairwise($ranked, $names, $strength, $probs);
        $xTable = $this->sectionExpectedTable($teamIds, $names, $strengthRank, $probs);
        $mc = $this->sectionMonteCarlo($teamIds, $names, $strength, $strengthRank, $matrices, $runs);
        $this->sectionVerdict($mc);

        if ($this->option('json')) {
            $this->dumpJson($game, $league, $floor, $runs, $names, $strengthRank, $rating, $strength, $xTable, $mc);
        }

        return self::SUCCESS;
    }

    private function resolveGame(): ?Game
    {
        $gameId = $this->argument('game');
        if ($gameId) {
            $game = Game::find($gameId);
            if (! $game) {
                $this->error("Game {$gameId} not found.");

                return null;
            }

            return $game;
        }

        $game = Game::query()->orderByDesc('created_at')->first();
        if (! $game) {
            $this->error('No games exist. Seed a game first or pass a game id.');

            return null;
        }

        return $game;
    }

    /**
     * Best XI in a 4-3-3 shape, by overall_score — mirrors
     * AIMatchResolver::selectRotatedXI (minus transient injury/fitness rotation,
     * which is irrelevant for a structural squad-strength snapshot).
     *
     * @param  Collection<int, GamePlayer>  $players
     * @return Collection<int, GamePlayer>
     */
    private function bestEleven(Collection $players): Collection
    {
        if ($players->count() <= 11) {
            return $players->values();
        }

        $score = fn (GamePlayer $p): float => (float) $p->overall_score;
        $grouped = $players->groupBy('position_group');
        $requirements = ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 3, 'Forward' => 3];

        $selected = collect();
        foreach ($requirements as $group => $count) {
            $selected = $selected->merge(
                ($grouped->get($group) ?? collect())->sortByDesc($score)->take($count)
            );
        }

        if ($selected->count() < 11) {
            $ids = $selected->pluck('id')->all();
            $selected = $selected->merge(
                $players->reject(fn ($p) => in_array($p->id, $ids, true))
                    ->sortByDesc($score)
                    ->take(11 - $selected->count())
            );
        }

        return $selected->values();
    }

    /**
     * Weighted XI mean on the 0..100 scale: overall·0.95 + morale·0.05, summed
     * over the XI and divided by the full squad size of 11 (so a short XI scores
     * lower) — identical to the engine's calculateTeamStrength before /100.
     *
     * @param  Collection<int, GamePlayer>  $xi
     */
    private function weightedRating(Collection $xi): float
    {
        if ($xi->isEmpty()) {
            return 0.0;
        }

        $wOverall = (float) config('match_simulation.strength_weight_overall', 0.95);
        $wMorale = (float) config('match_simulation.strength_weight_morale', 0.05);

        $sum = 0.0;
        foreach ($xi as $p) {
            $sum += ($p->overall_score * $wOverall) + ($p->morale * $wMorale);
        }

        return $sum / 11.0;
    }

    /**
     * Convert a 0..100 rating to the engine's normalized strength. Without
     * --floor this is the production `rating/100` (zero baseline). With --floor
     * it previews the rescale `(rating - floor)/(100 - floor)`, which re-expands
     * the ratio between teams because no real squad rates below ~50.
     */
    private function toStrength(float $rating, int $xiCount, ?float $floor): float
    {
        if ($xiCount < 7) {
            return 0.30; // matches the engine's depleted-lineup fallback
        }

        // Same rescale the production engines apply (floor 0/null = no-op).
        return MatchOutcomeModel::applyFloor($rating / 100.0, $floor ?? 0.0);
    }

    /**
     * Build P(W/D/L) and the score matrix for every ordered fixture.
     *
     * @param  array<int, string>  $teamIds
     * @param  array<string, float>  $strength
     * @return array{0: array<string, array<string, array{home: float, draw: float, away: float, homeGoals: float, awayGoals: float}>>, 1: array<string, array<string, array<int, array{0: int, 1: int, 2: float}>>>}
     */
    private function precomputeFixtures(array $teamIds, array $strength): array
    {
        $probs = [];
        $matrices = [];
        $overrides = $this->overrides();

        foreach ($teamIds as $home) {
            foreach ($teamIds as $away) {
                if ($home === $away) {
                    continue;
                }
                [$hxg, $axg] = MatchOutcomeModel::expectedGoals($strength[$home], $strength[$away], $this->neutral, $overrides);
                $matrix = MatchOutcomeModel::scoreProbabilityMatrix($hxg, $axg);
                $matrices[$home][$away] = $matrix;
                $probs[$home][$away] = MatchOutcomeModel::outcomeProbabilities($matrix);
            }
        }

        return [$probs, $matrices];
    }

    /** @return array{skill_dominance?: float} */
    private function overrides(): array
    {
        return $this->skillDominance !== null ? ['skill_dominance' => $this->skillDominance] : [];
    }

    private function printContext(Game $game, string $league, int $teams, ?float $floor, int $runs): void
    {
        $dom = $this->skillDominance ?? (float) config('match_simulation.skill_dominance', 2.4);
        $this->line('');
        $this->info('=== Squad-strength realism diagnostic ===');
        $this->line("game: {$game->id}   league: {$league}   teams: {$teams}   season: {$game->season}");
        $this->line("skill_dominance: {$dom}" . ($this->skillDominance !== null ? ' (override)' : '')
            . '   base_goals: ' . config('match_simulation.base_goals')
            . '   home_advantage: ' . ($this->neutral ? '0 (neutral)' : config('match_simulation.home_advantage_goals')));
        $this->line('strength floor: ' . ($floor === null ? 'none (rating/100)' : "{$floor}  →  (rating-{$floor})/(100-{$floor})")
            . '   form noise (std-dev): ' . ($this->stdDev === null ? 'none — AI league path' : $this->stdDev . ' (user-match approximation)'));
        $this->line("monte-carlo seasons: {$runs}");
    }

    /**
     * @param  array<int, string>  $ranked
     * @param  array<string, string>  $names
     * @param  array<string, float>  $rating
     * @param  array<string, float>  $strength
     */
    private function sectionInputSpread(array $ranked, array $names, array $rating, array $strength): void
    {
        $this->line('');
        $this->info('— 1. Input spread (how different are the squads?) —');

        $rows = [];
        foreach ($ranked as $idx => $tid) {
            $rows[] = [
                $idx + 1,
                $this->shortName($names[$tid] ?? $tid),
                number_format($rating[$tid], 1),
                number_format($strength[$tid], 3),
            ];
        }
        $this->table(['#', 'Team', 'XI rating', 'Strength'], $rows);

        $strengths = array_values($strength);
        $top = max($strengths);
        $bottom = min($strengths);
        $mean = array_sum($strengths) / count($strengths);
        $variance = 0.0;
        foreach ($strengths as $s) {
            $variance += ($s - $mean) ** 2;
        }
        $cov = $mean > 0 ? sqrt($variance / count($strengths)) / $mean : 0.0;
        $ratio = $bottom > 0 ? $top / $bottom : INF;

        $this->line(sprintf(
            '  top strength %.3f  /  bottom strength %.3f  →  top:bottom ratio %.3f',
            $top, $bottom, $ratio
        ));
        $this->line(sprintf('  coefficient of variation: %.1f%%   (xG gap of top vs bottom scales as ratio^(2·skill_dominance))', $cov * 100));
        $this->line('  ↳ the closer this ratio is to 1.000, the more every match tends toward a coin flip.');
    }

    /**
     * @param  array<int, string>  $ranked
     * @param  array<string, string>  $names
     * @param  array<string, float>  $strength
     * @param  array<string, array<string, array{home: float, draw: float, away: float, homeGoals: float, awayGoals: float}>>  $probs
     */
    private function sectionPairwise(array $ranked, array $names, array $strength, array $probs): void
    {
        $this->line('');
        $this->info('— 2. Pairwise win probabilities (analytical, exact) —');

        $n = count($ranked);
        $mid = intdiv($n, 2);
        $matchups = [
            ['Strongest (H) v Weakest (A)', $ranked[0], $ranked[$n - 1]],
            ['Weakest (H) v Strongest (A)', $ranked[$n - 1], $ranked[0]],
            ['1st (H) v 2nd (A)', $ranked[0], $ranked[1]],
            ['Mid (H) v Mid+1 (A)', $ranked[$mid - 1], $ranked[$mid]],
            ['2nd-last (H) v 3rd-last (A)', $ranked[$n - 2], $ranked[$n - 3]],
        ];

        $rows = [];
        foreach ($matchups as [$label, $home, $away]) {
            $o = $probs[$home][$away];
            $rows[] = [
                $label,
                $this->shortName($names[$home] ?? $home) . ' v ' . $this->shortName($names[$away] ?? $away),
                sprintf('%.2f–%.2f', $o['homeGoals'], $o['awayGoals']),
                sprintf('%.0f%%', $o['home'] * 100),
                sprintf('%.0f%%', $o['draw'] * 100),
                sprintf('%.0f%%', $o['away'] * 100),
            ];
        }
        $this->table(['Matchup', 'Teams', 'xG', 'Home win', 'Draw', 'Away win'], $rows);
        $this->line('  ↳ a healthy league shows the top team strongly favoured vs the bottom and near-even games only between true peers.');
    }

    /**
     * Deterministic expected-points table: each team's mean points if every
     * fixture paid out its probability-weighted result. Over a 20-team double
     * round-robin this is directly comparable to a real 38-game points total.
     *
     * @param  array<int, string>  $teamIds
     * @param  array<string, string>  $names
     * @param  array<string, int>  $strengthRank
     * @param  array<string, array<string, array{home: float, draw: float, away: float, homeGoals: float, awayGoals: float}>>  $probs
     * @return array<int, array{team: string, name: string, xpts: float, strengthRank: int}>
     */
    private function sectionExpectedTable(array $teamIds, array $names, array $strengthRank, array $probs): array
    {
        $this->line('');
        $this->info('— 3. Expected points table (analytical, no variance) —');

        $xpts = array_fill_keys($teamIds, 0.0);
        foreach ($teamIds as $home) {
            foreach ($teamIds as $away) {
                if ($home === $away) {
                    continue;
                }
                $o = $probs[$home][$away];
                $xpts[$home] += 3 * $o['home'] + $o['draw'];
                $xpts[$away] += 3 * $o['away'] + $o['draw'];
            }
        }

        $order = $teamIds;
        usort($order, fn ($a, $b) => $xpts[$b] <=> $xpts[$a]);

        $table = [];
        $rows = [];
        foreach ($order as $idx => $tid) {
            $pos = $idx + 1;
            $delta = $strengthRank[$tid] - $pos;
            $rows[] = [
                $pos,
                $this->shortName($names[$tid] ?? $tid),
                number_format($xpts[$tid], 1),
                $strengthRank[$tid],
                $delta === 0 ? '·' : sprintf('%+d', $delta),
            ];
            $table[] = ['team' => $tid, 'name' => $names[$tid] ?? $tid, 'xpts' => $xpts[$tid], 'strengthRank' => $strengthRank[$tid]];
        }
        $this->table(['#', 'Team', 'xPts', 'Str rank', 'Δ'], $rows);

        $champion = $xpts[$order[0]];
        $last = $xpts[$order[count($order) - 1]];
        $this->line(sprintf(
            '  champion xPts %.1f   last xPts %.1f   1st–last gap %.1f   (Δ column = strength rank minus finishing position; · = as expected)',
            $champion, $last, $champion - $last
        ));

        return $table;
    }

    /**
     * Monte-Carlo the season `runs` times, sampling every fixture's scoreline
     * from its distribution, to expose the spread and predictability a player
     * actually experiences across seasons.
     *
     * @param  array<int, string>  $teamIds
     * @param  array<string, string>  $names
     * @param  array<string, float>  $strength
     * @param  array<string, int>  $strengthRank
     * @param  array<string, array<string, array<int, array{0: int, 1: int, 2: float}>>>  $matrices
     * @return array<string, mixed>
     */
    private function sectionMonteCarlo(array $teamIds, array $names, array $strength, array $strengthRank, array $matrices, int $runs): array
    {
        $this->line('');
        $this->info("— 4. Season distribution over {$runs} Monte-Carlo seasons —");

        $strongestId = array_search(1, $strengthRank, true);
        $n = count($teamIds);

        $titleCount = array_fill_keys($teamIds, 0);
        $championPts = [];
        $lastPts = [];
        $spreads = [];
        $spearmans = [];
        $strongestOutsideTop4 = 0;
        $overrides = $this->overrides();

        $bar = $this->output->createProgressBar($runs);
        $bar->start();

        for ($r = 0; $r < $runs; $r++) {
            $pts = array_fill_keys($teamIds, 0);

            foreach ($teamIds as $home) {
                foreach ($teamIds as $away) {
                    if ($home === $away) {
                        continue;
                    }

                    if ($this->stdDev === null) {
                        [$hg, $ag] = MatchOutcomeModel::sampleScore($matrices[$home][$away]);
                    } else {
                        // Per-match form noise (user-match approximation): perturb
                        // each team's strength by a Gaussian whose sd is shrunk by
                        // √11 — the central-limit spread of 11 iid per-player rolls.
                        $hs = $strength[$home] * $this->noiseFactor();
                        $as = $strength[$away] * $this->noiseFactor();
                        [$hxg, $axg] = MatchOutcomeModel::expectedGoals($hs, $as, $this->neutral, $overrides);
                        [$hg, $ag] = MatchOutcomeModel::sampleScoreline($hxg, $axg);
                    }

                    if ($hg > $ag) {
                        $pts[$home] += 3;
                    } elseif ($hg < $ag) {
                        $pts[$away] += 3;
                    } else {
                        $pts[$home] += 1;
                        $pts[$away] += 1;
                    }
                }
            }

            // Final order: points desc, ties broken by strength (a stand-in for
            // goal difference) so positions are deterministic.
            $order = $teamIds;
            usort($order, fn ($a, $b) => ($pts[$b] <=> $pts[$a]) ?: ($strength[$b] <=> $strength[$a]));

            $position = [];
            foreach ($order as $idx => $tid) {
                $position[$tid] = $idx + 1;
            }

            $titleCount[$order[0]]++;
            $championPts[] = $pts[$order[0]];
            $lastPts[] = $pts[$order[$n - 1]];
            $spreads[] = $pts[$order[0]] - $pts[$order[$n - 1]];
            $spearmans[] = $this->spearman($strengthRank, $position, $teamIds);
            if ($position[$strongestId] > 4) {
                $strongestOutsideTop4++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');

        $strongestTitlePct = 100.0 * $titleCount[$strongestId] / $runs;
        $strongestOutsidePct = 100.0 * $strongestOutsideTop4 / $runs;

        $metrics = [
            'spearman_mean' => $this->mean($spearmans),
            'spearman_p10' => $this->percentile($spearmans, 10),
            'spearman_p90' => $this->percentile($spearmans, 90),
            'champion_pts_mean' => $this->mean($championPts),
            'champion_pts_p10' => $this->percentile($championPts, 10),
            'champion_pts_p90' => $this->percentile($championPts, 90),
            'last_pts_mean' => $this->mean($lastPts),
            'spread_mean' => $this->mean($spreads),
            'strongest_title_pct' => $strongestTitlePct,
            'strongest_outside_top4_pct' => $strongestOutsidePct,
            'strongest_team' => $names[$strongestId] ?? $strongestId,
        ];

        $this->table(['Metric', 'Value', 'What it tells you'], [
            ['Strength→position correlation (ρ)', sprintf('%.2f  [p10 %.2f … p90 %.2f]', $metrics['spearman_mean'], $metrics['spearman_p10'], $metrics['spearman_p90']), 'Higher = stronger teams finish higher. ~0 = coin flip.'],
            ['Champion points', sprintf('%.0f  [p10 %.0f … p90 %.0f]', $metrics['champion_pts_mean'], $metrics['champion_pts_p10'], $metrics['champion_pts_p90']), 'How dominant the winner is.'],
            ['Last-place points', sprintf('%.0f', $metrics['last_pts_mean']), 'How adrift the worst team is.'],
            ['1st–last points gap', sprintf('%.0f', $metrics['spread_mean']), 'Overall table stretch.'],
            ['Strongest team wins title', sprintf('%.0f%%', $metrics['strongest_title_pct']), 'Does the best squad usually win?'],
            ['Strongest team finishes 5th+', sprintf('%.0f%%', $metrics['strongest_outside_top4_pct']), 'How often the best squad is squeezed out of the top 4.'],
        ]);

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $mc
     */
    private function sectionVerdict(array $mc): void
    {
        $this->line('');
        $this->info('— 5. Verdict vs top-flight reference bands —');

        $rows = [
            $this->verdictRow('Champion points', $mc['champion_pts_mean'], self::BENCHMARK_CHAMPION_PTS),
            $this->verdictRow('Last-place points', $mc['last_pts_mean'], self::BENCHMARK_LAST_PTS),
            $this->verdictRow('1st–last gap', $mc['spread_mean'], self::BENCHMARK_SPREAD),
            $this->verdictRowMin('Strength→position ρ', $mc['spearman_mean'], self::BENCHMARK_MIN_SPEARMAN),
        ];
        $this->table(['Metric', 'Simulated', 'Reference', 'Flag'], $rows);
        $this->line('  Bands are loose realism targets (≈ La Liga), not hard rules. ⚠ on the gap/ρ rows is the signature of strength flattening.');
        $this->line('  ↳ Re-run with --floor=45 or --floor=50 to preview how the strength-floor rescale widens the spread and lifts ρ.');
    }

    /**
     * @param  array{0: int, 1: int}  $band
     * @return array<int, string>
     */
    private function verdictRow(string $label, float $value, array $band): array
    {
        $ok = $value >= $band[0] && $value <= $band[1];

        return [$label, number_format($value, 1), "{$band[0]}–{$band[1]}", $ok ? '✓' : '⚠'];
    }

    /**
     * @return array<int, string>
     */
    private function verdictRowMin(string $label, float $value, float $min): array
    {
        return [$label, number_format($value, 2), "≥ {$min}", $value >= $min ? '✓' : '⚠'];
    }

    /**
     * Spearman rank correlation. Both inputs are dense distinct ranks (1..n), so
     * the exact form ρ = 1 − 6Σd² / (n(n²−1)) applies with no tie correction.
     *
     * @param  array<string, int>  $rankA
     * @param  array<string, int>  $rankB
     * @param  array<int, string>  $ids
     */
    private function spearman(array $rankA, array $rankB, array $ids): float
    {
        $n = count($ids);
        if ($n < 2) {
            return 0.0;
        }

        $d2 = 0;
        foreach ($ids as $id) {
            $d = $rankA[$id] - $rankB[$id];
            $d2 += $d * $d;
        }

        return 1.0 - (6.0 * $d2) / ($n * ($n * $n - 1));
    }

    /**
     * One Gaussian form factor centred on 1.0, sd = stdDev/√11, clamped loosely.
     * Box-Muller; consumes two mt_rand() calls.
     */
    private function noiseFactor(): float
    {
        $u1 = max(1e-9, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
        $sd = $this->stdDev / sqrt(11.0);

        return max(0.85, min(1.15, 1.0 + $z * $sd));
    }

    /**
     * @param  array<int, float|int>  $values
     */
    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /**
     * @param  array<int, float|int>  $values
     */
    private function percentile(array $values, float $p): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $idx = ($p / 100) * (count($values) - 1);
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);
        if ($lo === $hi) {
            return (float) $values[$lo];
        }
        $frac = $idx - $lo;

        return $values[$lo] * (1 - $frac) + $values[$hi] * $frac;
    }

    private function shortName(string $name): string
    {
        return mb_strlen($name) > 22 ? mb_substr($name, 0, 21) . '…' : $name;
    }

    /**
     * @param  array<string, string>  $names
     * @param  array<string, int>  $strengthRank
     * @param  array<string, float>  $rating
     * @param  array<string, float>  $strength
     * @param  array<int, array{team: string, name: string, xpts: float, strengthRank: int}>  $xTable
     * @param  array<string, mixed>  $mc
     */
    private function dumpJson(Game $game, string $league, ?float $floor, int $runs, array $names, array $strengthRank, array $rating, array $strength, array $xTable, array $mc): void
    {
        $path = (string) $this->option('json');

        $teams = [];
        foreach ($strengthRank as $tid => $rank) {
            $teams[] = [
                'team_id' => $tid,
                'name' => $names[$tid] ?? $tid,
                'strength_rank' => $rank,
                'rating' => round($rating[$tid], 2),
                'strength' => round($strength[$tid], 4),
            ];
        }

        $payload = [
            'game_id' => $game->id,
            'league' => $league,
            'season' => $game->season,
            'config' => [
                'skill_dominance' => $this->skillDominance ?? (float) config('match_simulation.skill_dominance', 2.4),
                'floor' => $floor,
                'std_dev' => $this->stdDev,
                'neutral' => $this->neutral,
                'runs' => $runs,
            ],
            'teams' => $teams,
            'expected_table' => array_map(fn ($r) => [
                'team_id' => $r['team'],
                'name' => $r['name'],
                'xpts' => round($r['xpts'], 2),
                'strength_rank' => $r['strengthRank'],
            ], $xTable),
            'monte_carlo' => $mc,
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('');
        $this->line("JSON written to {$path}");
    }
}
