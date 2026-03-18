<?php

namespace App\Modules\Manager\Services;

use App\Models\ManagerStats;
use App\Models\ManagerTrophy;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ManagerProfileService
{
    /**
     * Get all trophies for a user, ordered by most recent season.
     */
    public function getTrophies(User $user): Collection
    {
        return ManagerTrophy::where('user_id', $user->id)
            ->with(['competition', 'team'])
            ->orderByDesc('season')
            ->get();
    }

    /**
     * Aggregate career stats across all of a user's games.
     *
     * @return array{matches: int, wins: int, draws: int, losses: int, win_percentage: float, best_streak: int, seasons: int}
     */
    public function getCareerStats(User $user): array
    {
        $stats = ManagerStats::where('user_id', $user->id)
            ->selectRaw('SUM(matches_played) as total_matches')
            ->selectRaw('SUM(matches_won) as total_wins')
            ->selectRaw('SUM(matches_drawn) as total_draws')
            ->selectRaw('SUM(matches_lost) as total_losses')
            ->selectRaw('MAX(longest_unbeaten_streak) as best_streak')
            ->selectRaw('SUM(seasons_completed) as total_seasons')
            ->first();

        $totalMatches = (int) ($stats->total_matches ?? 0);

        return [
            'matches' => $totalMatches,
            'wins' => (int) ($stats->total_wins ?? 0),
            'draws' => (int) ($stats->total_draws ?? 0),
            'losses' => (int) ($stats->total_losses ?? 0),
            'win_percentage' => $totalMatches > 0
                ? round(((int) $stats->total_wins / $totalMatches) * 100, 1)
                : 0,
            'best_streak' => (int) ($stats->best_streak ?? 0),
            'seasons' => (int) ($stats->total_seasons ?? 0),
        ];
    }
}
