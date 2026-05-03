<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUsers
{
    public function __invoke(Request $request)
    {
        $search = $request->query('search');

        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $searchLower = mb_strtolower($search);
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->appends($request->query());

        // games_count comes from the tenant plane; resolved as a separate
        // query keyed by user_id and attached to each User row. Replaces the
        // previous User::withCount('games') subquery, which would cross planes
        // once User and Game live on different connections.
        $gameCounts = Game::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->pluck('count', 'user_id');

        foreach ($users as $user) {
            $user->games_count = (int) ($gameCounts[$user->id] ?? 0);
        }

        return view('admin.users', [
            'users' => $users,
            'search' => $search,
        ]);
    }
}
