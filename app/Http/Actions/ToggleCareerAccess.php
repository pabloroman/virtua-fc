<?php

namespace App\Http\Actions;

use App\Models\User;
use Illuminate\Http\Request;

class ToggleCareerAccess
{
    public function __invoke(Request $request, string $userId)
    {
        $user = User::findOrFail($userId);
        $user->update(['has_career_access' => ! $user->has_career_access]);

        return back();
    }
}
