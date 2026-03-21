<?php

namespace App\Http\Actions;

use App\Models\User;
use Illuminate\Http\Request;

class ToggleDatabaseEditing
{
    public function __invoke(Request $request, string $userId)
    {
        $user = User::findOrFail($userId);
        $user->update(['can_edit_database' => ! $user->can_edit_database]);

        return back();
    }
}
