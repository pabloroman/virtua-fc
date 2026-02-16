<?php

namespace App\Console\Commands;

use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Game\Services\MatchSimulator;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use Illuminate\Console\Command;

class SimulateMatch extends Command
{
    protected $signature = 'match:simulate
                            {--match= : Match ID to simulate}
                            {--home= : Home team ID (alternative to match)}
                            {--away= : Away team ID (alternative to match)}
                            {--game= : Game ID (required if using team IDs)}
                            {--runs=100 : Number of simulations to run}
                            {--formation=4-4-2 : Formation for both teams}
                            {--home-mentality=balanced : Home team mentality (defensive/balanced/attacking)}
                            {--away-mentality=balanced : Away team mentality (defensive/balanced/attacking)}
                            {--verbose-results : Show individual match results}';

    protected $description = 'Run multiple match simulations to test the algorithm';

    public function handle(MatchSimulator $simulator): int
    {
        $runs = (int) $this->option('runs');
        $matchId = $this->option('match');

        // Get teams and players
        if ($matchId) {
            $match = GameMatch::with(['homeTeam', 'awayTeam'])->find($matchId);
            if (!$match) {
                $this->error("Match not found: {$matchId}");
                return 1;
            }

            $homeTeam = $match->homeTeam;
            $awayTeam = $match->awayTeam;
            $gameId = $match->game_id;

            // Get lineup players or all players
            $homeLineupIds = $match->home_lineup ?? [];
            $awayLineupIds = $match->away_lineup ?? [];

            $homePlayers = !empty($homeLineupIds)
                ? GamePlayer::where('game_id', $gameId)->whereIn('id', $homeLineupIds)->get()
                : GamePlayer::where('game_id', $gameId)->where('team_id', $homeTeam->id)->get();

            $awayPlayers = !empty($awayLineupIds)
                ? GamePlayer::where('game_id', $gameId)->whereIn('id', $awayLineupIds)->get()
                : GamePlayer::where('game_id', $gameId)->where('team_id', $awayTeam->id)->get();

        } else {
            $homeTeamId = $this->option('home');
            $awayTeamId = $this->option('away');
            $gameId = $this->option('game');

            if (!$homeTeamId || !$awayTeamId || !$gameId) {
                $this->error('Either --match or (--home, --away, --game) are required');
                return 1;
            }

            $homeTeam = Team::find($homeTeamId);
            $awayTeam = Team::find($awayTeamId);

            if (!$homeTeam || !$awayTeam) {
                $this->error('Teams not found');
                return 1;
            }

            $homePlayers = GamePlayer::where('game_id', $gameId)->where('team_id', $homeTeamId)->get();
            $awayPlayers = GamePlayer::where('game_id', $gameId)->where('team_id', $awayTeamId)->get();
        }

        // Parse options
        $formation = Formation::tryFrom($this->option('formation')) ?? Formation::F_4_4_2;
        $homeMentality = Mentality::tryFrom($this->option('home-mentality')) ?? Mentality::BALANCED;
        $awayMentality = Mentality::tryFrom($this->option('away-mentality')) ?? Mentality::BALANCED;

        // Calculate team strengths
        $homeStrength = $homePlayers->avg('overall_score');
        $awayStrength = $awayPlayers->avg('overall_score');

        // Display setup info
        $this->info('');
        $this->info('=== Match Simulation ===');
        $this->table(
            ['', 'Home', 'Away'],
            [
                ['Team', $homeTeam->name, $awayTeam->name],
                ['Players', $homePlayers->count(), $awayPlayers->count()],
                ['Avg Rating', round($homeStrength, 1), round($awayStrength, 1)],
                ['Formation', $formation->label(), $formation->label()],
                ['Mentality', $homeMentality->label(), $awayMentality->label()],
            ]
        );

        // Show config
        $this->info('');
        $this->info('Current Config (config/match_simulation.php):');
        $this->table(
            ['Parameter', 'Value'],
            [
                ['base_goals', config('match_simulation.base_goals', 1.3)],
                ['ratio_exponent', config('match_simulation.ratio_exponent', 2.0)],
                ['home_advantage_goals', config('match_simulation.home_advantage_goals', 0.15)],
                ['performance_std_dev', config('match_simulation.performance_std_dev', 0.05)],
                ['performance_min', config('match_simulation.performance_min', 0.90)],
                ['performance_max', config('match_simulation.performance_max', 1.10)],
            ]
        );

        // Run simulations
        $this->info('');
        $this->info("Running {$runs} simulations...");
        $this->newLine();

        $results = [
            'home_wins' => 0,
            'draws' => 0,
            'away_wins' => 0,
            'home_goals' => [],
            'away_goals' => [],
            'total_goals' => [],
            'scorelines' => [],
        ];

        $progressBar = $this->output->createProgressBar($runs);
        $progressBar->start();

        for ($i = 0; $i < $runs; $i++) {
            $result = $simulator->simulate(
                $homeTeam,
                $awayTeam,
                $homePlayers,
                $awayPlayers,
                $formation,
                $formation,
                $homeMentality,
                $awayMentality
            );

            // Track results
            if ($result->homeScore > $result->awayScore) {
                $results['home_wins']++;
            } elseif ($result->awayScore > $result->homeScore) {
                $results['away_wins']++;
            } else {
                $results['draws']++;
            }

            $results['home_goals'][] = $result->homeScore;
            $results['away_goals'][] = $result->awayScore;
            $results['total_goals'][] = $result->homeScore + $result->awayScore;

            $scoreline = "{$result->homeScore}-{$result->awayScore}";
            $results['scorelines'][$scoreline] = ($results['scorelines'][$scoreline] ?? 0) + 1;

            if ($this->option('verbose-results')) {
                $hn = $homeTeam->name;
                $an = $awayTeam->name;
                $this->line(" [{$i}] {$hn} {$result->homeScore} - {$result->awayScore} {$an}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Calculate statistics
        $homeWinPct = round($results['home_wins'] / $runs * 100, 1);
        $drawPct = round($results['draws'] / $runs * 100, 1);
        $awayWinPct = round($results['away_wins'] / $runs * 100, 1);

        $avgHomeGoals = round(array_sum($results['home_goals']) / $runs, 2);
        $avgAwayGoals = round(array_sum($results['away_goals']) / $runs, 2);
        $avgTotalGoals = round(array_sum($results['total_goals']) / $runs, 2);

        // Get display names (use short_name or fallback to name)
        $homeName = $homeTeam->name;
        $awayName = $awayTeam->name;

        // Display results
        $this->info('=== Results ===');
        $this->table(
            ['Outcome', 'Count', 'Percentage'],
            [
                ["{$homeName} Wins", $results['home_wins'], "{$homeWinPct}%"],
                ['Draws', $results['draws'], "{$drawPct}%"],
                ["{$awayName} Wins", $results['away_wins'], "{$awayWinPct}%"],
            ]
        );

        $this->info('');
        $this->info('=== Goals ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ["Avg {$homeName} Goals", $avgHomeGoals],
                ["Avg {$awayName} Goals", $avgAwayGoals],
                ['Avg Total Goals', $avgTotalGoals],
                ["Max {$homeName} Goals", max($results['home_goals'])],
                ["Max {$awayName} Goals", max($results['away_goals'])],
            ]
        );

        // Show most common scorelines
        arsort($results['scorelines']);
        $topScorelines = array_slice($results['scorelines'], 0, 10, true);

        $this->info('');
        $this->info('=== Most Common Scorelines ===');
        $scorelineRows = [];
        foreach ($topScorelines as $score => $count) {
            $pct = round($count / $runs * 100, 1);
            $scorelineRows[] = [$score, $count, "{$pct}%"];
        }
        $this->table(['Scoreline', 'Count', 'Percentage'], $scorelineRows);

        // Show clean sheet stats
        $homeCleanSheets = count(array_filter($results['away_goals'], fn($g) => $g === 0));
        $awayCleanSheets = count(array_filter($results['home_goals'], fn($g) => $g === 0));
        $bothScored = count(array_filter(array_map(
            fn($h, $a) => $h > 0 && $a > 0,
            $results['home_goals'],
            $results['away_goals']
        ), fn($v) => $v));

        $this->info('');
        $this->info('=== Other Stats ===');
        $this->table(
            ['Stat', 'Count', 'Percentage'],
            [
                ["{$homeName} Clean Sheets", $homeCleanSheets, round($homeCleanSheets / $runs * 100, 1) . '%'],
                ["{$awayName} Clean Sheets", $awayCleanSheets, round($awayCleanSheets / $runs * 100, 1) . '%'],
                ['Both Teams Scored', $bothScored, round($bothScored / $runs * 100, 1) . '%'],
            ]
        );

        return 0;
    }
}
