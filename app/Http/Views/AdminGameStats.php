<?php

namespace App\Http\Views;

use App\Modules\Analytics\Services\GameStatsService;
use Illuminate\Http\Request;

class AdminGameStats
{
    public function __invoke(Request $request, GameStatsService $stats)
    {
        return view('admin.game-stats', [
            'teamPopularity' => $stats->getTeamPopularity(),
            'seasonProgress' => $stats->getSeasonProgress(),
        ]);
    }
}
