<?php

namespace App\Http\Views;

use App\Models\User;

class ShowManagerProfile
{
    public function __invoke(string $username)
    {
        $user = User::where('username', $username)
            ->where('is_profile_public', true)
            ->firstOrFail();

        $user->load(['games.team', 'games.competition']);

        return view('profile.show', compact('user'));
    }
}
