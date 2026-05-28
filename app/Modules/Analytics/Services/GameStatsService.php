<?php

namespace App\Modules\Analytics\Services;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameStatsService
{
    public function getClubPopularity(int $limit = 15): Collection
    {
        $clubTeamIds = Team::where('type', '!=', 'national')->pluck('id');

        return Game::select('team_id', DB::raw('COUNT(*) as picks'))
            ->whereIn('team_id', $clubTeamIds)
            ->groupBy('team_id')
            ->orderByDesc('picks')
            ->with('team:id,name,image,type,country')
            ->limit($limit)
            ->get();
    }

    public function getNationalTeamPopularity(int $limit = 15): Collection
    {
        $nationalTeamIds = Team::where('type', 'national')->pluck('id');

        return Game::select('team_id', DB::raw('COUNT(*) as picks'))
            ->whereIn('team_id', $nationalTeamIds)
            ->groupBy('team_id')
            ->orderByDesc('picks')
            ->with('team:id,name,image,type,country')
            ->limit($limit)
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
