<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Match\Support\MatchOutcomeModel;
use App\Modules\Match\Support\PaperStrength;
use App\Modules\Player\Services\PlayerConditionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Measure whether squad morale spreads realistically across a season once it
 * reacts to under/overperformance — i.e. do struggling sides actually sink into
 * the narrative `morale_low` zone, do winning sides earn `morale_high`, and does
 * the default sit quietly in between?
 *
 * Read-only projection. It drives the SAME expected-points math the sim uses
 * (paper strength → {@see MatchOutcomeModel} → 3·P(win)+1·P(draw)) and the SAME
 * morale term ({@see PlayerConditionService::underperformanceMoraleDelta}), then
 * Monte-Carlos a double round-robin, evolving each team's squad-average morale
 * match by match. So the numbers reflect production behaviour, not a
 * re-implementation.
 *
 * Model notes (deliberate simplifications — this calibrates the NEW term, not the
 * whole morale model): morale is tracked as a single squad-average accumulator
 * per team, seeded from the game's current morale. Each match applies the flat
 * result change (at its expected midpoint) plus the under/overperformance term,
 * both scaled by SQUAD_RESULT_BLEND to approximate a squad where only the XI
 * feels the result at full weight, minus a small bench-frustration drag.
 * Individual goal/assist morale is omitted (it roughly nets out across a squad).
 *
 * The `--threshold-*` flags preview the narrative zone cut-offs without touching
 * code, making this the calibration instrument for both the term constants (in
 * {@see PlayerConditionService}) and the `moodCandidates` thresholds.
 */
class DiagnoseMoraleRealism extends Command
{
    protected $signature = 'app:diagnose-morale-realism
        {game? : Game id (defaults to the most recently created game)}
        {--league= : Competition code, e.g. ESP1 (defaults to the game\'s own league)}
        {--runs=200 : Number of Monte-Carlo seasons}
        {--threshold-low=62 : Preview the morale_low narrative cut-off}
        {--threshold-high=85 : Preview the morale_high narrative cut-off}
        {--json= : Also write the full metric set as JSON to this path}';

    protected $description = 'Measure whether morale spreads across the low/neutral/high narrative zones once it reacts to under/overperformance (read-only).';

    /** Expected value of the flat result change (midpoints of MORALE_WIN/DRAW/LOSS). */
    private const FLAT_WIN = 6.0;

    private const FLAT_DRAW = 1.0;

    private const FLAT_LOSS = -2.5;

    /**
     * Squad-average blend: only the ~11 who play feel the result at full weight,
     * the ~14 on the bench at half — a 25-man squad averages ≈ 0.66.
     */
    private const SQUAD_RESULT_BLEND = 0.66;

    /** Per-match squad-average drag from bench frustration (~14 players losing ~1). */
    private const BENCH_DRAG = 0.5;

    private const MIN_MORALE = 50;

    private const MAX_MORALE = 100;

    private float $thresholdLow = 62.0;

    private float $thresholdHigh = 85.0;

    public function handle(): int
    {
        $game = $this->resolveGame();
        if (! $game) {
            return self::FAILURE;
        }

        $league = (string) ($this->option('league') ?: $game->competition_id);
        $runs = max(1, (int) $this->option('runs'));
        $this->thresholdLow = (float) $this->option('threshold-low');
        $this->thresholdHigh = (float) $this->option('threshold-high');

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

        // Per-team paper strength (best XI) and current squad-average morale seed.
        $strength = [];
        $startMorale = [];
        foreach ($teamIds as $tid) {
            $squad = $playersByTeam->get($tid, collect());
            $strength[$tid] = PaperStrength::estimate($this->bestEleven($squad));
            $startMorale[$tid] = $this->squadAverageMorale($squad);
        }

        // Rank by strength descending → strengthRank[teamId] = 1..n, for tiering.
        $ranked = $teamIds;
        usort($ranked, fn ($a, $b) => $strength[$b] <=> $strength[$a]);
        $strengthRank = [];
        foreach ($ranked as $idx => $tid) {
            $strengthRank[$tid] = $idx + 1;
        }

        [$probs, $matrices] = $this->precomputeFixtures($teamIds, $strength);

        $this->printContext($game, $league, count($teamIds), $runs, $startMorale);
        $mc = $this->sectionMonteCarlo($teamIds, $names, $startMorale, $strengthRank, $probs, $matrices, $runs);
        $this->sectionVerdict($mc);

        if ($this->option('json')) {
            $this->dumpJson($game, $league, $runs, $mc);
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
     * Best XI in a 4-3-3 shape by overall_score — the likely starting eleven, so
     * paper strength reflects who takes the field. Mirrors the strength diagnostic.
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
     * @param  Collection<int, GamePlayer>  $squad
     */
    private function squadAverageMorale(Collection $squad): float
    {
        if ($squad->isEmpty()) {
            return 80.0;
        }

        return (float) $squad->avg(fn (GamePlayer $p) => $p->morale);
    }

    /**
     * Build P(W/D/L) + score matrix for every ordered fixture, once.
     *
     * @param  array<int, string>  $teamIds
     * @param  array<string, float>  $strength
     * @return array{0: array<string, array<string, array{home: float, draw: float, away: float, homeGoals: float, awayGoals: float}>>, 1: array<string, array<string, array<int, array{0: int, 1: int, 2: float}>>>}
     */
    private function precomputeFixtures(array $teamIds, array $strength): array
    {
        $probs = [];
        $matrices = [];

        foreach ($teamIds as $home) {
            foreach ($teamIds as $away) {
                if ($home === $away) {
                    continue;
                }
                [$hxg, $axg] = MatchOutcomeModel::expectedGoals($strength[$home], $strength[$away]);
                $matrix = MatchOutcomeModel::scoreProbabilityMatrix($hxg, $axg);
                $matrices[$home][$away] = $matrix;
                $probs[$home][$away] = MatchOutcomeModel::outcomeProbabilities($matrix);
            }
        }

        return [$probs, $matrices];
    }

    /**
     * @param  array<string, float>  $startMorale
     */
    private function printContext(Game $game, string $league, int $teams, int $runs, array $startMorale): void
    {
        $seedAvg = $startMorale === [] ? 0.0 : array_sum($startMorale) / count($startMorale);
        $this->line('');
        $this->info('=== Morale-realism diagnostic ===');
        $this->line("game: {$game->id}   league: {$league}   teams: {$teams}   season: {$game->season}");
        $this->line(sprintf('seed squad-avg morale: %.1f (game current state)   morale band: [%d, %d]', $seedAvg, self::MIN_MORALE, self::MAX_MORALE));
        $this->line(sprintf('narrative zones: low < %.0f   neutral   high > %.0f', $this->thresholdLow, $this->thresholdHigh));
        $this->line("monte-carlo seasons: {$runs}");
        $this->line('  model: squad-average accumulator; flat result (midpoint) + underperformance term, blended '
            . self::SQUAD_RESULT_BLEND . ', minus ' . self::BENCH_DRAG . ' bench drag/match. Individual events omitted.');
    }

    /**
     * Monte-Carlo the double round-robin `runs` times, evolving each team's
     * squad-average morale, and gather: end-of-season morale by strength tier,
     * how often the under/overperformance term fires and its magnitude, and the
     * share of team-weeks landing in each narrative zone.
     *
     * @param  array<int, string>  $teamIds
     * @param  array<string, string>  $names
     * @param  array<string, float>  $startMorale
     * @param  array<string, int>  $strengthRank
     * @param  array<string, array<string, array{home: float, draw: float, away: float, homeGoals: float, awayGoals: float}>>  $probs
     * @param  array<string, array<string, array<int, array{0: int, 1: int, 2: float}>>>  $matrices
     * @return array<string, mixed>
     */
    private function sectionMonteCarlo(array $teamIds, array $names, array $startMorale, array $strengthRank, array $probs, array $matrices, int $runs): array
    {
        $n = count($teamIds);
        $tierSize = max(1, intdiv($n, 3));

        // Team-week zone tallies and term-fire stats, accumulated across all runs.
        $zoneLow = 0;
        $zoneNeutral = 0;
        $zoneHigh = 0;
        $observations = 0;
        $underFires = 0;
        $overFires = 0;
        $underMagSum = 0.0;
        $overMagSum = 0.0;
        $matchApplications = 0;

        // End-of-season morale collected per team across runs.
        $finalMorale = array_fill_keys($teamIds, []);

        $bar = $this->output->createProgressBar($runs);
        $bar->start();

        for ($r = 0; $r < $runs; $r++) {
            $morale = $startMorale;

            // Randomize fixture order so morale trajectories vary between runs.
            $fixtures = [];
            foreach ($teamIds as $home) {
                foreach ($teamIds as $away) {
                    if ($home !== $away) {
                        $fixtures[] = [$home, $away];
                    }
                }
            }
            shuffle($fixtures);

            foreach ($fixtures as [$home, $away]) {
                [$hg, $ag] = MatchOutcomeModel::sampleScore($matrices[$home][$away]);

                $o = $probs[$home][$away];
                $homeExpected = 3 * $o['home'] + $o['draw'];
                $awayExpected = 3 * $o['away'] + $o['draw'];

                foreach ([[$home, $hg, $ag, $homeExpected], [$away, $ag, $hg, $awayExpected]] as [$tid, $gf, $ga, $expected]) {
                    $actual = $gf > $ga ? 3 : ($gf === $ga ? 1 : 0);
                    $delta = $actual - $expected;

                    $flat = $gf > $ga ? self::FLAT_WIN : ($gf === $ga ? self::FLAT_DRAW : self::FLAT_LOSS);
                    $term = PlayerConditionService::underperformanceMoraleDelta($delta, self::SQUAD_RESULT_BLEND);

                    if ($term < 0) {
                        $underFires++;
                        $underMagSum += -$term;
                    } elseif ($term > 0) {
                        $overFires++;
                        $overMagSum += $term;
                    }
                    $matchApplications++;

                    $morale[$tid] = max(self::MIN_MORALE, min(
                        self::MAX_MORALE,
                        $morale[$tid] + $flat * self::SQUAD_RESULT_BLEND + $term - self::BENCH_DRAG,
                    ));

                    // Team-week observation: which narrative zone is this side in?
                    $observations++;
                    if ($morale[$tid] < $this->thresholdLow) {
                        $zoneLow++;
                    } elseif ($morale[$tid] > $this->thresholdHigh) {
                        $zoneHigh++;
                    } else {
                        $zoneNeutral++;
                    }
                }
            }

            foreach ($teamIds as $tid) {
                $finalMorale[$tid][] = $morale[$tid];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');

        // Section: end-of-season morale by strength tier.
        $this->line('');
        $this->info('— 1. End-of-season squad-average morale by strength tier —');
        $tiers = ['Top third' => [], 'Middle third' => [], 'Bottom third' => []];
        foreach ($teamIds as $tid) {
            $meanFinal = $this->mean($finalMorale[$tid]);
            $tier = match (true) {
                $strengthRank[$tid] <= $tierSize => 'Top third',
                $strengthRank[$tid] <= 2 * $tierSize => 'Middle third',
                default => 'Bottom third',
            };
            $tiers[$tier][] = $meanFinal;
        }
        $tierRows = [];
        foreach ($tiers as $label => $vals) {
            if ($vals === []) {
                continue;
            }
            $tierRows[] = [$label, count($vals), number_format($this->mean($vals), 1), number_format(min($vals), 1), number_format(max($vals), 1)];
        }
        $this->table(['Tier', 'Teams', 'Mean morale', 'Min', 'Max'], $tierRows);
        $this->line('  ↳ healthy spread = strong sides high, weak/slumping sides pulled toward the floor.');

        // Section: term-fire statistics.
        $this->line('');
        $this->info('— 2. Under/overperformance term firing —');
        $underPct = $matchApplications > 0 ? 100.0 * $underFires / $matchApplications : 0.0;
        $overPct = $matchApplications > 0 ? 100.0 * $overFires / $matchApplications : 0.0;
        $this->table(['Direction', 'Fires', '% of side-matches', 'Mean magnitude'], [
            ['Underperformance (−)', $underFires, sprintf('%.1f%%', $underPct), $underFires ? number_format($underMagSum / $underFires, 2) : '—'],
            ['Overperformance (+)', $overFires, sprintf('%.1f%%', $overPct), $overFires ? number_format($overMagSum / $overFires, 2) : '—'],
        ]);
        $this->line('  ↳ magnitudes reflect the squad blend (' . self::SQUAD_RESULT_BLEND . '); the XI itself feels the full term.');

        // Section: narrative-zone occupancy.
        $this->line('');
        $this->info('— 3. Narrative-zone occupancy (team-weeks) —');
        $lowPct = $observations > 0 ? 100.0 * $zoneLow / $observations : 0.0;
        $neutralPct = $observations > 0 ? 100.0 * $zoneNeutral / $observations : 0.0;
        $highPct = $observations > 0 ? 100.0 * $zoneHigh / $observations : 0.0;
        $this->table(['Zone', 'Cut-off', 'Team-weeks', 'Share'], [
            ['morale_low', sprintf('< %.0f', $this->thresholdLow), $zoneLow, sprintf('%.1f%%', $lowPct)],
            ['neutral', sprintf('%.0f–%.0f', $this->thresholdLow, $this->thresholdHigh), $zoneNeutral, sprintf('%.1f%%', $neutralPct)],
            ['morale_high', sprintf('> %.0f', $this->thresholdHigh), $zoneHigh, sprintf('%.1f%%', $highPct)],
        ]);

        return [
            'runs' => $runs,
            'seed_avg' => $startMorale === [] ? 0.0 : array_sum($startMorale) / count($startMorale),
            'zone_low_pct' => $lowPct,
            'zone_neutral_pct' => $neutralPct,
            'zone_high_pct' => $highPct,
            'under_fire_pct' => $underPct,
            'over_fire_pct' => $overPct,
            'under_mean_mag' => $underFires ? $underMagSum / $underFires : 0.0,
            'over_mean_mag' => $overFires ? $overMagSum / $overFires : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $mc
     */
    private function sectionVerdict(array $mc): void
    {
        $this->line('');
        $this->info('— 4. Verdict —');

        $allZonesLive = $mc['zone_low_pct'] > 1.0 && $mc['zone_neutral_pct'] > 1.0 && $mc['zone_high_pct'] > 1.0;
        $termFires = $mc['under_fire_pct'] > 2.0;

        $this->table(['Check', 'Result', 'Flag'], [
            ['All three narrative zones populated (>1%)', sprintf('low %.1f%% / neutral %.1f%% / high %.1f%%', $mc['zone_low_pct'], $mc['zone_neutral_pct'], $mc['zone_high_pct']), $allZonesLive ? '✓' : '⚠'],
            ['Underperformance term fires meaningfully (>2%)', sprintf('%.1f%%', $mc['under_fire_pct']), $termFires ? '✓' : '⚠'],
        ]);
        $this->line('  ⚠ on zone occupancy = a threshold (or the term magnitude) needs retuning so morale actually reaches that zone.');
        $this->line('  ↳ Re-run with --threshold-low / --threshold-high to preview cut-offs, or tune the term constants in PlayerConditionService.');
    }

    /**
     * @param  array<int, float|int>  $values
     */
    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /**
     * @param  array<string, mixed>  $mc
     */
    private function dumpJson(Game $game, string $league, int $runs, array $mc): void
    {
        $path = (string) $this->option('json');
        $payload = [
            'game_id' => $game->id,
            'league' => $league,
            'season' => $game->season,
            'config' => [
                'runs' => $runs,
                'threshold_low' => $this->thresholdLow,
                'threshold_high' => $this->thresholdHigh,
            ],
            'metrics' => $mc,
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('');
        $this->line("JSON written to {$path}");
    }
}
