<?php

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGameOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $gameId = $request->route('gameId');

        if ($gameId) {
            $game = Game::find($gameId);

            if (! $game || $game->user_id !== $request->user()->id) {
                abort(403);
            }
        }

        return $next($request);
    }
}
