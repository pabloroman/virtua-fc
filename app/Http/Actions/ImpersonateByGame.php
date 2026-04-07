<?php

namespace App\Http\Actions;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateByGame
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'game_id' => ['required', 'uuid', 'exists:games,id'],
        ]);

        $game = Game::with('user')->findOrFail($request->input('game_id'));

        if ($game->user->id === $request->user()->id) {
            return back();
        }

        $request->session()->put('impersonating_from', $request->user()->id);

        Auth::login($game->user);

        return redirect()->route('dashboard');
    }
}
