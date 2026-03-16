<?php

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureGameOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $gameId = $request->route('gameId');

        if ($gameId) {
            $ownerId = Cache::rememberForever("game_owner:{$gameId}", function () use ($gameId) {
                return Game::where('id', $gameId)->value('user_id');
            });

            if (! $ownerId || $ownerId !== $request->user()->id) {
                abort(403);
            }
        }

        return $next($request);
    }
}
