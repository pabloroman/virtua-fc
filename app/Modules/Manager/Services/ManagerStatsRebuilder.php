<?php

namespace App\Modules\Manager\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\ManagerStats;
use App\Models\SeasonArchive;
use App\Models\User;

/**
 * Rebuilds the manager_stats aggregate for a user's career games from the
 * underlying match history that already exists locally.
 *
 * Why this exists: the beta→prod migration imported `manager_stats` keyed on
 * `user_id`, which collapsed the per-game rows the schema actually stores into
 * a single survivor (see TableManifest history). Match results — the source of
 * truth — were imported intact, but the derived counters and unbeaten streak
 * were not, so already-migrated users see partially lost career achievements
 * on the leaderboard until their aggregate is recomputed.
 *
 * Source data per game:
 *   - Prior seasons: `season_archives.match_results` JSON, one entry per match
 *     involving any team in the game (already trimmed by SeasonArchiveProcessor
 *     to the user's team's matches).
 *   - Current season: live `game_matches` where `played = true` and the user's
 *     team is home or away.
 *
 * The replay mirrors UpdateManagerStats / ManagerStats::recordResult exactly so
 * a rebuild produces the same numbers as if every match had been processed
 * through the runtime listener.
 *
 * `seasons_completed` cannot be derived from matches; we use the count of
 * `season_archives` rows for the game, which is what `LeaderboardStatsProcessor`
 * increments alongside the archive write at season close.
 */
class ManagerStatsRebuilder
{
    /**
     * Rebuild every career game owned by the user.
     *
     * @return array{games:int,skipped:int,results:list<array{game_id:string,matches_played:int,longest_unbeaten_streak:int,seasons_completed:int}>}
     */
    public function rebuildForUser(User $user): array
    {
        $games = Game::where('user_id', $user->id)->get();

        $summary = ['games' => 0, 'skipped' => 0, 'results' => []];

        foreach ($games as $game) {
            if (! $game->isCareerMode()) {
                $summary['skipped']++;
                continue;
            }

            $stats = $this->rebuildForGame($game);
            $summary['games']++;
            $summary['results'][] = [
                'game_id' => $game->id,
                'matches_played' => $stats->matches_played,
                'longest_unbeaten_streak' => $stats->longest_unbeaten_streak,
                'seasons_completed' => $stats->seasons_completed,
            ];
        }

        return $summary;
    }

    /**
     * Rebuild the manager_stats row for a single career game.
     *
     * Idempotent: existing rows are updated in place by `game_id` so the row's
     * UUID stays stable (and any FK relying on it survives).
     */
    public function rebuildForGame(Game $game): ManagerStats
    {
        $teamId = $game->team_id;

        $aggregate = [
            'matches_played' => 0,
            'matches_won' => 0,
            'matches_drawn' => 0,
            'matches_lost' => 0,
            'current_unbeaten_streak' => 0,
            'longest_unbeaten_streak' => 0,
        ];

        foreach ($this->matchesInOrder($game) as $match) {
            $result = $this->determineResult($match, $teamId);
            if ($result === null) {
                continue;
            }

            $aggregate['matches_played']++;
            match ($result) {
                'win' => $aggregate['matches_won']++,
                'draw' => $aggregate['matches_drawn']++,
                'loss' => $aggregate['matches_lost']++,
            };

            if ($result === 'loss') {
                $aggregate['current_unbeaten_streak'] = 0;
            } else {
                $aggregate['current_unbeaten_streak']++;
                if ($aggregate['current_unbeaten_streak'] > $aggregate['longest_unbeaten_streak']) {
                    $aggregate['longest_unbeaten_streak'] = $aggregate['current_unbeaten_streak'];
                }
            }
        }

        $aggregate['win_percentage'] = $aggregate['matches_played'] > 0
            ? round(($aggregate['matches_won'] / $aggregate['matches_played']) * 100, 2)
            : 0;

        $aggregate['seasons_completed'] = SeasonArchive::where('game_id', $game->id)->count();

        $stats = ManagerStats::firstOrNew(['game_id' => $game->id]);
        $stats->user_id = $game->user_id;
        $stats->team_id = $teamId;
        $stats->fill($aggregate);
        $stats->save();

        return $stats;
    }

    /**
     * All played matches for this game's user team, in chronological order.
     *
     * Combines archived seasons (stored as JSON on `season_archives`) with the
     * live current season (`game_matches`). Each entry is a plain associative
     * array with the same key shape so `determineResult` has one code path.
     *
     * @return iterable<array<string,mixed>>
     */
    private function matchesInOrder(Game $game): iterable
    {
        $rows = [];

        // Archived seasons. SeasonArchiveProcessor already filters
        // match_results down to the user's team, but we re-check defensively
        // — older archives or future changes shouldn't be allowed to skew
        // counters by silently feeding in third-party matches.
        $archives = SeasonArchive::where('game_id', $game->id)
            ->orderBy('season')
            ->get(['season', 'match_results']);

        foreach ($archives as $archive) {
            foreach ($archive->match_results ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'date' => (string) ($row['date'] ?? ''),
                    'data' => $row,
                ];
            }
        }

        // Current season — live game_matches not yet archived.
        GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                                 ->orWhere('away_team_id', $game->team_id))
            ->orderBy('scheduled_date')
            ->chunk(500, function ($chunk) use (&$rows) {
                foreach ($chunk as $match) {
                    $rows[] = [
                        'date' => $match->scheduled_date->toDateString(),
                        'data' => [
                            'home_team_id' => $match->home_team_id,
                            'away_team_id' => $match->away_team_id,
                            'home_score' => $match->home_score,
                            'away_score' => $match->away_score,
                            'is_extra_time' => $match->is_extra_time,
                            'home_score_et' => $match->home_score_et,
                            'away_score_et' => $match->away_score_et,
                            'home_score_penalties' => $match->home_score_penalties,
                            'away_score_penalties' => $match->away_score_penalties,
                        ],
                    ];
                }
            });

        // Stable sort by date. Same-day ordering is unspecified (cup +
        // league can share a date) but cannot affect totals; only the
        // win/draw/loss boundary of streaks, which is acceptable.
        usort($rows, fn (array $a, array $b) => $a['date'] <=> $b['date']);

        foreach ($rows as $row) {
            yield $row['data'];
        }
    }

    /**
     * Mirrors UpdateManagerStats::determineResult but operates on a plain
     * array so the same logic covers both Eloquent rows (live matches) and
     * JSON entries (archived match_results).
     *
     * Returns null when the match doesn't involve the user's team or when the
     * score fields are missing.
     *
     * @return 'win'|'draw'|'loss'|null
     */
    private function determineResult(array $row, string $teamId): ?string
    {
        $isHome = ($row['home_team_id'] ?? null) === $teamId;
        $isAway = ($row['away_team_id'] ?? null) === $teamId;
        if (! $isHome && ! $isAway) {
            return null;
        }

        $homePens = $row['home_score_penalties'] ?? null;
        $awayPens = $row['away_score_penalties'] ?? null;
        if ($homePens !== null && $awayPens !== null) {
            $teamPens = $isHome ? $homePens : $awayPens;
            $oppPens = $isHome ? $awayPens : $homePens;

            return $teamPens > $oppPens ? 'win' : 'loss';
        }

        $homeEt = $row['home_score_et'] ?? null;
        $awayEt = $row['away_score_et'] ?? null;
        if (! empty($row['is_extra_time']) && $homeEt !== null && $awayEt !== null) {
            $teamScore = $isHome ? $homeEt : $awayEt;
            $oppScore = $isHome ? $awayEt : $homeEt;

            if ($teamScore > $oppScore) {
                return 'win';
            }
            if ($oppScore > $teamScore) {
                return 'loss';
            }

            return 'draw';
        }

        $homeScore = $row['home_score'] ?? null;
        $awayScore = $row['away_score'] ?? null;
        if ($homeScore === null || $awayScore === null) {
            return null;
        }

        $teamScore = $isHome ? $homeScore : $awayScore;
        $oppScore = $isHome ? $awayScore : $homeScore;

        if ($teamScore > $oppScore) {
            return 'win';
        }
        if ($oppScore > $teamScore) {
            return 'loss';
        }

        return 'draw';
    }
}
