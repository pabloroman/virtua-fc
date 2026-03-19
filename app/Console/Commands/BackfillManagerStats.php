<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\ManagerStats;
use App\Models\SeasonArchive;
use Illuminate\Console\Command;

class BackfillManagerStats extends Command
{
    protected $signature = 'app:backfill-manager-stats';

    protected $description = 'Backfill manager leaderboard stats from existing match history (one row per game)';

    public function handle(): int
    {
        $total = Game::where('game_mode', Game::MODE_CAREER)->count();

        $this->info("Processing {$total} career games...");

        $bar = $this->output->createProgressBar($total);

        Game::where('game_mode', Game::MODE_CAREER)->chunk(10, function ($games) use ($bar) {
            foreach ($games as $game) {
                $this->backfillGame($game);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    private function backfillGame(Game $game): void
    {
        $won = 0;
        $drawn = 0;
        $lost = 0;
        $currentStreak = 0;
        $longestStreak = 0;

        // Phase 1: Process archived seasons (oldest first)
        // Only select match_results to avoid loading large JSON blobs
        // (player_season_stats, final_standings, match_events_archive)
        $seasonsCompleted = 0;

        SeasonArchive::where('game_id', $game->id)
            ->select('id', 'game_id', 'season', 'match_results')
            ->orderBy('season')
            ->each(function (SeasonArchive $archive) use ($game, &$won, &$drawn, &$lost, &$currentStreak, &$longestStreak, &$seasonsCompleted) {
                $seasonsCompleted++;

                $archivedMatches = collect($archive->match_results ?? [])
                    ->filter(fn (array $m) => $m['home_team_id'] === $game->team_id || $m['away_team_id'] === $game->team_id)
                    ->sortBy(['date', 'round_number'])
                    ->values();

                foreach ($archivedMatches as $matchData) {
                    $result = $this->determineResultFromArchive($matchData, $game->team_id);

                    if ($result === null) {
                        continue;
                    }

                    $this->applyResult($result, $won, $drawn, $lost, $currentStreak, $longestStreak);
                }
            });

        // Phase 2: Process current season matches (not yet archived)
        $matches = GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->orderBy('scheduled_date')
            ->orderBy('round_number')
            ->get();

        foreach ($matches as $match) {
            $result = $this->determineResult($match, $game->team_id);

            if ($result === null) {
                continue;
            }

            $this->applyResult($result, $won, $drawn, $lost, $currentStreak, $longestStreak);
        }

        $total = $won + $drawn + $lost;

        if ($total === 0) {
            return;
        }

        ManagerStats::updateOrCreate(
            ['game_id' => $game->id],
            [
                'user_id' => $game->user_id,
                'team_id' => $game->team_id,
                'matches_played' => $total,
                'matches_won' => $won,
                'matches_drawn' => $drawn,
                'matches_lost' => $lost,
                'win_percentage' => round(($won / $total) * 100, 2),
                'current_unbeaten_streak' => $currentStreak,
                'longest_unbeaten_streak' => $longestStreak,
                'seasons_completed' => $seasonsCompleted,
            ],
        );
    }

    private function applyResult(string $result, int &$won, int &$drawn, int &$lost, int &$currentStreak, int &$longestStreak): void
    {
        match ($result) {
            'win' => $won++,
            'draw' => $drawn++,
            'loss' => $lost++,
        };

        if ($result === 'loss') {
            $currentStreak = 0;
        } else {
            $currentStreak++;
            if ($currentStreak > $longestStreak) {
                $longestStreak = $currentStreak;
            }
        }
    }

    /**
     * @return 'win'|'draw'|'loss'|null
     */
    private function determineResultFromArchive(array $match, string $teamId): ?string
    {
        $isHome = $match['home_team_id'] === $teamId;

        // Penalties (enriched archives)
        if (isset($match['home_score_penalties'], $match['away_score_penalties'])
            && $match['home_score_penalties'] !== null
            && $match['away_score_penalties'] !== null) {
            $teamPen = $isHome ? $match['home_score_penalties'] : $match['away_score_penalties'];
            $oppPen = $isHome ? $match['away_score_penalties'] : $match['home_score_penalties'];

            return $teamPen > $oppPen ? 'win' : 'loss';
        }

        // Extra time (enriched archives)
        if (! empty($match['is_extra_time'])
            && isset($match['home_score_et'], $match['away_score_et'])
            && $match['home_score_et'] !== null
            && $match['away_score_et'] !== null) {
            $teamScore = $isHome ? $match['home_score_et'] : $match['away_score_et'];
            $oppScore = $isHome ? $match['away_score_et'] : $match['home_score_et'];

            if ($teamScore > $oppScore) {
                return 'win';
            }
            if ($oppScore > $teamScore) {
                return 'loss';
            }

            return 'draw';
        }

        // Regular time (always present)
        $teamScore = $isHome ? $match['home_score'] : $match['away_score'];
        $oppScore = $isHome ? $match['away_score'] : $match['home_score'];

        if ($teamScore > $oppScore) {
            return 'win';
        }
        if ($oppScore > $teamScore) {
            return 'loss';
        }

        return 'draw';
    }

    /**
     * @return 'win'|'draw'|'loss'|null
     */
    private function determineResult(GameMatch $match, string $teamId): ?string
    {
        $isHome = $match->isHomeTeam($teamId);

        // Penalties
        if ($match->home_score_penalties !== null && $match->away_score_penalties !== null) {
            $teamPen = $isHome ? $match->home_score_penalties : $match->away_score_penalties;
            $oppPen = $isHome ? $match->away_score_penalties : $match->home_score_penalties;

            return $teamPen > $oppPen ? 'win' : 'loss';
        }

        // Extra time
        if ($match->is_extra_time && $match->home_score_et !== null && $match->away_score_et !== null) {
            $teamScore = $isHome ? $match->home_score_et : $match->away_score_et;
            $oppScore = $isHome ? $match->away_score_et : $match->home_score_et;

            if ($teamScore > $oppScore) {
                return 'win';
            }
            if ($oppScore > $teamScore) {
                return 'loss';
            }

            return 'draw';
        }

        // Regular time
        $teamScore = $isHome ? $match->home_score : $match->away_score;
        $oppScore = $isHome ? $match->away_score : $match->home_score;

        if ($teamScore > $oppScore) {
            return 'win';
        }
        if ($oppScore > $teamScore) {
            return 'loss';
        }

        return 'draw';
    }
}
