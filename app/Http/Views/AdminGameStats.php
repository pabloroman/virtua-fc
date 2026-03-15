<?php

namespace App\Http\Views;

use App\Services\GameStatsService;
use Illuminate\Http\Request;

class AdminGameStats
{
    public function __invoke(Request $request, GameStatsService $stats)
    {
        return view('admin.game-stats', [
            'teamPopularity' => $stats->getTeamPopularity(),
            'formations' => $stats->getFormationPreferences(),
            'mentalities' => $stats->getMentalityDistribution(),
            'seasonProgress' => $stats->getSeasonProgress(),
        ]);
    }
}
