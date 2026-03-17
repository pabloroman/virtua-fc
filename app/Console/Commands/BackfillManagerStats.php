<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\ManagerStats;
use App\Models\SeasonArchive;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillManagerStats extends Command
{
    protected $signature = 'app:backfill-manager-stats';

    protected $description = 'Backfill manager leaderboard stats from existing match history';

    public function handle(): int
    {
        $users = User::whereHas('games', function ($query) {
            $query->where('game_mode', Game::MODE_CAREER);
        })->get();

        $this->info("Processing {$users->count()} users with career games...");

        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            $this->backfillUser($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    private function backfillUser(User $user): void
    {
        $careerGames = $user->games()->where('game_mode', Game::MODE_CAREER)->get();

        $won = 0;
        $drawn = 0;
        $lost = 0;
        $currentStreak = 0;
        $longestStreak = 0;
        $seasonsCompleted = 0;

        foreach ($careerGames as $game) {
            // Count completed seasons from archives
            $seasonsCompleted += SeasonArchive::where('game_id', $game->id)->count();

            // Get all played matches for the user's team, ordered chronologically
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
        }

        $total = $won + $drawn + $lost;

        if ($total === 0) {
            return;
        }

        ManagerStats::updateOrCreate(
            ['user_id' => $user->id],
            [
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
