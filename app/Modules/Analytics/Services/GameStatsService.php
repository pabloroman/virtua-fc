<?php

namespace App\Modules\Analytics\Services;

use App\Models\Game;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameStatsService
{
    public function getTeamPopularity(int $limit = 15): Collection
    {
        return Game::select('team_id', DB::raw('COUNT(*) as picks'))
            ->groupBy('team_id')
            ->orderByDesc('picks')
            ->with('team:id,name,image')
            ->limit($limit)
            ->get();
    }

    public function getFormationPreferences(): Collection
    {
        return DB::table('game_matches')
            ->join('games', 'game_matches.game_id', '=', 'games.id')
            ->where('game_matches.played', true)
            ->selectRaw("
                CASE
                    WHEN games.team_id = game_matches.home_team_id THEN game_matches.home_formation
                    WHEN games.team_id = game_matches.away_team_id THEN game_matches.away_formation
                END as formation,
                COUNT(*) as usage_count
            ")
            ->groupBy('formation')
            ->having('formation', '!=', null)
            ->orderByDesc('usage_count')
            ->get();
    }

    public function getMentalityDistribution(): Collection
    {
        return DB::table('game_matches')
            ->join('games', 'game_matches.game_id', '=', 'games.id')
            ->where('game_matches.played', true)
            ->selectRaw("
                CASE
                    WHEN games.team_id = game_matches.home_team_id THEN game_matches.home_mentality
                    WHEN games.team_id = game_matches.away_team_id THEN game_matches.away_mentality
                END as mentality,
                COUNT(*) as usage_count
            ")
            ->groupBy('mentality')
            ->having('mentality', '!=', null)
            ->orderByDesc('usage_count')
            ->get();
    }

    public function getSeasonProgress(): Collection
    {
        return Game::selectRaw('season, COUNT(*) as count')
            ->whereNotNull('setup_completed_at')
            ->groupBy('season')
            ->orderBy('season')
            ->get();
    }
}
