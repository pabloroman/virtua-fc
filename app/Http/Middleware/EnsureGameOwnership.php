<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Models\TournamentSummary;
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
            $ownerId = Cache::remember("game_owner:{$gameId}", 3600, function () use ($gameId) {
                return Game::where('id', $gameId)->value('user_id');
            });

            if (! $ownerId || (int) $ownerId !== (int) $request->user()->id) {
                abort(403);
            }

            $deletingGame = Game::where('id', $gameId)->whereNotNull('deleting_at')->first(['game_mode', 'user_id']);

            if ($deletingGame) {
                if ($deletingGame->isTournamentMode()) {
                    $summary = TournamentSummary::where('user_id', $deletingGame->user_id)
                        ->latest('created_at')
                        ->first();

                    if ($summary) {
                        return redirect()->route('tournament-summary.show', $summary->id);
                    }
                }

                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
