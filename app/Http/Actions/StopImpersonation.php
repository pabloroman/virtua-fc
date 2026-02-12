<?php

namespace App\Http\Actions;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StopImpersonation
{
    public function __invoke(Request $request)
    {
        $adminId = $request->session()->pull('impersonating_from');

        if (! $adminId) {
            return redirect()->route('dashboard');
        }

        $admin = User::findOrFail($adminId);

        Auth::login($admin);

        return redirect()->route('admin.users');
    }
}
