<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUsers
{
    public function __invoke(Request $request)
    {
        $users = User::withCount('games')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.users', [
            'users' => $users,
        ]);
    }
}
