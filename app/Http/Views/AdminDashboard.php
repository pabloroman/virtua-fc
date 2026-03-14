<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboard
{
    public function __invoke(Request $request)
    {
        $totalUsers = User::count();
        $totalGames = Game::count();
        $newUsers7d = User::where('created_at', '>=', now()->subDays(7))->count();
        $newGames7d = Game::where('created_at', '>=', now()->subDays(7))->count();

        return view('admin.dashboard', [
            'totalUsers' => $totalUsers,
            'totalGames' => $totalGames,
            'newUsers7d' => $newUsers7d,
            'newGames7d' => $newGames7d,
        ]);
    }
}
