<?php

namespace App\Http\Actions;

use App\Models\User;
use Illuminate\Http\Request;

class ToggleTournamentAccess
{
    public function __invoke(Request $request, string $userId)
    {
        $user = User::findOrFail($userId);
        $user->update(['has_tournament_access' => ! $user->has_tournament_access]);

        return back();
    }
}
