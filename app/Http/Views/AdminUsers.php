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

        $query = User::withCount('games');

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

        return view('admin.users', [
            'users' => $users,
            'search' => $search,
        ]);
    }
}
