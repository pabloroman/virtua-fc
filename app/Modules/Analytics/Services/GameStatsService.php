<?php

namespace App\Modules\Analytics\Services;

use App\Models\Game;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameStatsService
{
    public function getClubPopularity(int $limit = 15): Collection
    {
        return Game::select('team_id', DB::raw('COUNT(*) as picks'))
            ->whereHas('team', fn ($q) => $q->where('type', '!=', 'national'))
            ->groupBy('team_id')
            ->orderByDesc('picks')
            ->with('team:id,name,image,type,country')
            ->limit($limit)
            ->get();
    }

    public function getNationalTeamPopularity(int $limit = 15): Collection
    {
        return Game::select('team_id', DB::raw('COUNT(*) as picks'))
            ->whereHas('team', fn ($q) => $q->where('type', 'national'))
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
