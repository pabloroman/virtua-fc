<?php

namespace App\Console\Commands;

use App\Http\Actions\AdvanceMatchday;
use App\Modules\Season\Services\GameCreationService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\SeasonArchive;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StressTest extends Command
{
    protected $signature = 'app:stress-test
                            {--games=1 : Number of games to create and simulate}
                            {--seasons=1 : Number of seasons to simulate per game}
                            {--skip-creation : Skip game creation, use existing games}
                            {--seed : Re-seed reference data before starting}
                            {--csv= : Write detailed metrics to a CSV file}';

    protected $description = 'Stress test: create games and simulate seasons, measuring performance at each step';

    private array $metrics = [];

    public function handle(GameCreationService $gameCreationService): int
    {
        $gameCount = (int) $this->option('games');
        $seasonCount = (int) $this->option('seasons');
        $skipCreation = $this->option('skip-creation');
        $csvPath = $this->option('csv');

        $this->info("=== VirtuaFC Stress Test ===");
        $this->info("Games: {$gameCount} | Seasons: {$seasonCount}");
        $this->newLine();

        // Seed if requested
        if ($this->option('seed')) {
            $this->info('Seeding reference data...');
            $this->call('app:seed-reference-data', [
                '--fresh' => true,
            ]);
            $this->newLine();
        }

        // Capture baseline DB stats
        $this->reportDbStats('BASELINE');

        // Phase 1: Game creation
        $games = collect();
        if ($skipCreation) {
            $games = Game::whereNotNull('setup_completed_at')
                ->latest('created_at')
                ->limit($gameCount)
                ->get();

            if ($games->count() < $gameCount) {
                $this->error("Only found {$games->count()} existing games (requested {$gameCount}).");
                return Command::FAILURE;
            }

            $this->info("Using {$games->count()} existing games.");
        } else {
            $this->info("--- Phase 1: Creating {$gameCount} games ---");
            $games = $this->createGames($gameCount, $gameCreationService);

            if ($games->isEmpty()) {
                $this->error('Failed to create any games.');
                return Command::FAILURE;
            }
        }

        $this->reportDbStats('AFTER CREATION');

        // Phase 2: Simulate seasons
        $this->info("--- Phase 2: Simulating {$seasonCount} season(s) per game ---");

        $advanceAction = app(AdvanceMatchday::class);

        for ($season = 1; $season <= $seasonCount; $season++) {
            $this->info("  Season {$season}/{$seasonCount}:");

            foreach ($games as $gameIndex => $game) {
                $this->simulateOneSeason($game, $advanceAction, $season, $gameIndex + 1, $gameCount);
            }

            $this->reportDbStats("AFTER SEASON {$season}");
        }

        // Final report
        $this->newLine();
        $this->info('=== RESULTS ===');
        $this->printSummary($gameCount, $seasonCount);

        if ($csvPath) {
            $this->writeCsv($csvPath);
            $this->info("Detailed metrics written to: {$csvPath}");
        }

        return Command::SUCCESS;
    }

    private function createGames(int $count, GameCreationService $gameCreationService): \Illuminate\Support\Collection
    {
        $games = collect();

        // Find a team to use
        $team = Team::where('name', 'Real Madrid')->first() ?? Team::first();

        if (! $team) {
            $this->error('No teams found. Run with --seed first.');
            return $games;
        }

        for ($i = 0; $i < $count; $i++) {
            $t0 = microtime(true);
            $mem0 = memory_get_usage(true);

            // Create a user for each game
            $user = User::create([
                'name' => "StressTest User {$i}",
                'email' => "stress-test-{$i}-" . Str::random(6) . '@test.local',
                'password' => bcrypt('password'),
            ]);

            $game = $gameCreationService->create(
                userId: (string) $user->id,
                teamId: $team->id,
            );

            // Process the setup job synchronously
            $this->processQueuedJobs();

            $game->refresh();

            if (! $game->setup_completed_at) {
                $this->warn("  Game {$game->id} setup did not complete.");
                continue;
            }

            $elapsed = (microtime(true) - $t0) * 1000;
            $memDelta = (memory_get_usage(true) - $mem0) / 1024 / 1024;

            $this->recordMetric('game_creation', [
                'game_id' => $game->id,
                'game_number' => $i + 1,
                'elapsed_ms' => round($elapsed, 1),
                'memory_delta_mb' => round($memDelta, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            $this->line("    Game " . ($i + 1) . "/{$count} created in " . round($elapsed) . "ms (peak mem: " . round(memory_get_peak_usage(true) / 1024 / 1024) . "MB)");

            $games->push($game);
        }

        return $games;
    }

    private function simulateOneSeason(Game $game, AdvanceMatchday $advanceAction, int $seasonNumber, int $gameNumber, int $totalGames): void
    {
        $game->refresh();

        $seasonStart = microtime(true);
        $advances = 0;
        $batchTimings = [];

        $matchCountBefore = GameMatch::where('game_id', $game->id)->where('played', true)->count();

        while ($advances < 600) {
            $game->refresh();

            $hasMatches = GameMatch::where('game_id', $game->id)
                ->where('played', false)
                ->exists();

            if (! $hasMatches) {
                break;
            }

            $t0 = microtime(true);
            $mem0 = memory_get_usage(true);

            DB::enableQueryLog();
            $advanceAction($game->id);
            $queryCount = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();

            $elapsed = (microtime(true) - $t0) * 1000;

            $batchTimings[] = [
                'advance' => $advances + 1,
                'elapsed_ms' => round($elapsed, 1),
                'queries' => $queryCount,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ];

            $advances++;

            // Show progress every 20 advances
            if ($advances % 20 === 0) {
                $avgMs = round(collect($batchTimings)->avg('elapsed_ms'));
                $avgQ = round(collect($batchTimings)->avg('queries'));
                $this->line("    Game {$gameNumber}/{$totalGames}: {$advances} advances (avg {$avgMs}ms, {$avgQ} queries/advance)");
            }
        }

        $matchCountAfter = GameMatch::where('game_id', $game->id)->where('played', true)->count();
        $matchesPlayed = $matchCountAfter - $matchCountBefore;

        $seasonElapsed = (microtime(true) - $seasonStart) * 1000;

        // Check if season ended (triggers pipeline automatically)
        $game->refresh();
        $isSeasonComplete = ! GameMatch::where('game_id', $game->id)->where('played', false)->exists();

        // Record season metrics
        $timings = collect($batchTimings);
        $this->recordMetric('season', [
            'game_id' => $game->id,
            'game_number' => $gameNumber,
            'season_number' => $seasonNumber,
            'total_advances' => $advances,
            'matches_played' => $matchesPlayed,
            'total_ms' => round($seasonElapsed, 1),
            'avg_advance_ms' => round($timings->avg('elapsed_ms'), 1),
            'p50_advance_ms' => round($this->percentile($timings->pluck('elapsed_ms')->toArray(), 50), 1),
            'p95_advance_ms' => round($this->percentile($timings->pluck('elapsed_ms')->toArray(), 95), 1),
            'p99_advance_ms' => round($this->percentile($timings->pluck('elapsed_ms')->toArray(), 99), 1),
            'max_advance_ms' => round($timings->max('elapsed_ms'), 1),
            'avg_queries' => round($timings->avg('queries'), 1),
            'max_queries' => $timings->max('queries'),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'season_complete' => $isSeasonComplete,
        ]);

        $this->line(sprintf(
            "    Game %d/%d: Season %d done — %d advances, %d matches, %.1fs total (p50: %dms, p95: %dms, max: %dms)",
            $gameNumber,
            $totalGames,
            $seasonNumber,
            $advances,
            $matchesPlayed,
            $seasonElapsed / 1000,
            $this->percentile($timings->pluck('elapsed_ms')->toArray(), 50),
            $this->percentile($timings->pluck('elapsed_ms')->toArray(), 95),
            $timings->max('elapsed_ms'),
        ));
    }

    private function reportDbStats(string $label): void
    {
        $stats = [
            'game_players' => GamePlayer::count(),
            'game_matches' => GameMatch::count(),
            'match_events' => MatchEvent::count(),
            'season_archives' => SeasonArchive::count(),
            'games' => Game::count(),
        ];

        $dbSize = 'N/A';
        try {
            $result = DB::selectOne("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
            $dbSize = $result->size;
        } catch (\Throwable) {
            // ignore
        }

        $this->newLine();
        $this->info("[{$label}] DB Stats:");
        $this->table(
            ['Table', 'Rows'],
            collect($stats)->map(fn ($count, $table) => [$table, number_format($count)])->values()->toArray()
        );
        $this->line("  DB size: {$dbSize} | Memory: " . round(memory_get_usage(true) / 1024 / 1024) . "MB (peak: " . round(memory_get_peak_usage(true) / 1024 / 1024) . "MB)");
    }

    private function printSummary(int $gameCount, int $seasonCount): void
    {
        $seasonMetrics = collect($this->metrics)->where('type', 'season');

        if ($seasonMetrics->isEmpty()) {
            $this->warn('No season metrics recorded.');
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Games simulated', $gameCount],
                ['Seasons per game', $seasonCount],
                ['Total advances', $seasonMetrics->sum('data.total_advances')],
                ['Total matches', $seasonMetrics->sum('data.matches_played')],
                ['Total time', round($seasonMetrics->sum('data.total_ms') / 1000, 1) . 's'],
                ['Avg advance time (p50)', round($seasonMetrics->avg('data.p50_advance_ms')) . 'ms'],
                ['Avg advance time (p95)', round($seasonMetrics->avg('data.p95_advance_ms')) . 'ms'],
                ['Worst advance time', round($seasonMetrics->max('data.max_advance_ms')) . 'ms'],
                ['Avg queries/advance', round($seasonMetrics->avg('data.avg_queries'))],
                ['Max queries/advance', $seasonMetrics->max('data.max_queries')],
                ['Peak memory', $seasonMetrics->max('data.peak_memory_mb') . 'MB'],
            ]
        );
    }

    private function recordMetric(string $type, array $data): void
    {
        $this->metrics[] = ['type' => $type, 'data' => $data];
    }

    private function writeCsv(string $path): void
    {
        $seasonMetrics = collect($this->metrics)->where('type', 'season');

        $fp = fopen($path, 'w');
        fputcsv($fp, [
            'game_id', 'game_number', 'season_number', 'total_advances',
            'matches_played', 'total_ms', 'avg_advance_ms', 'p50_ms',
            'p95_ms', 'p99_ms', 'max_ms', 'avg_queries', 'max_queries',
            'peak_memory_mb',
        ]);

        foreach ($seasonMetrics as $m) {
            fputcsv($fp, [
                $m['data']['game_id'],
                $m['data']['game_number'],
                $m['data']['season_number'],
                $m['data']['total_advances'],
                $m['data']['matches_played'],
                $m['data']['total_ms'],
                $m['data']['avg_advance_ms'],
                $m['data']['p50_advance_ms'],
                $m['data']['p95_advance_ms'],
                $m['data']['p99_advance_ms'],
                $m['data']['max_advance_ms'],
                $m['data']['avg_queries'],
                $m['data']['max_queries'],
                $m['data']['peak_memory_mb'],
            ]);
        }

        fclose($fp);
    }

    private function percentile(array $values, float $pct): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ($pct / 100) * (count($values) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return $values[$lower];
        }

        $fraction = $index - $lower;

        return $values[$lower] + ($values[$upper] - $values[$lower]) * $fraction;
    }

    private function processQueuedJobs(): void
    {
        // Process queue jobs synchronously by running the queue worker
        $this->callSilently('queue:work', [
            '--once' => true,
            '--queue' => 'default',
        ]);
    }
}
