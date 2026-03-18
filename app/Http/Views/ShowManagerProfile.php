<?php

namespace App\Http\Views;

use App\Models\ManagerStats;
use App\Models\ManagerTrophy;
use App\Models\User;

class ShowManagerProfile
{
    public function __invoke(string $username)
    {
        $user = User::where('username', $username)
            ->where('is_profile_public', true)
            ->firstOrFail();

        $user->load(['games.team', 'games.competition']);

        $trophies = ManagerTrophy::where('user_id', $user->id)
            ->with(['competition', 'team'])
            ->orderByDesc('season')
            ->get();

        $careerStats = ManagerStats::where('user_id', $user->id)
            ->selectRaw('SUM(matches_played) as total_matches')
            ->selectRaw('SUM(matches_won) as total_wins')
            ->selectRaw('SUM(matches_drawn) as total_draws')
            ->selectRaw('SUM(matches_lost) as total_losses')
            ->selectRaw('MAX(longest_unbeaten_streak) as best_streak')
            ->selectRaw('SUM(seasons_completed) as total_seasons')
            ->first();

        $totalMatches = (int) ($careerStats->total_matches ?? 0);
        $winPercentage = $totalMatches > 0
            ? round(((int) $careerStats->total_wins / $totalMatches) * 100, 1)
            : 0;

        return view('profile.show', [
            'user' => $user,
            'trophies' => $trophies,
            'careerStats' => [
                'matches' => $totalMatches,
                'wins' => (int) ($careerStats->total_wins ?? 0),
                'draws' => (int) ($careerStats->total_draws ?? 0),
                'losses' => (int) ($careerStats->total_losses ?? 0),
                'win_percentage' => $winPercentage,
                'best_streak' => (int) ($careerStats->best_streak ?? 0),
                'seasons' => (int) ($careerStats->total_seasons ?? 0),
            ],
        ]);
    }
}
