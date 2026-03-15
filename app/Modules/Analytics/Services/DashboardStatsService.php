<?php

namespace App\Modules\Analytics\Services;

use App\Models\Game;
use App\Models\User;

class DashboardStatsService
{
    public function getSummary(): array
    {
        return [
            'totalUsers' => User::count(),
            'totalGames' => Game::count(),
            'newUsers7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'newGames7d' => Game::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
