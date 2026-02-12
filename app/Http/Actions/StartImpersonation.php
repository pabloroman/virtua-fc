<?php

namespace App\Http\Actions;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StartImpersonation
{
    public function __invoke(Request $request, string $userId)
    {
        $target = User::findOrFail($userId);

        if ($target->id === $request->user()->id) {
            return back();
        }

        $request->session()->put('impersonating_from', $request->user()->id);

        Auth::login($target);

        return redirect()->route('dashboard');
    }
}
