<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use Illuminate\Support\Facades\Log;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchdayAdvanceCoordinator $coordinator,
    ) {}

    public function __invoke(string $gameId)
    {
        // If the user is in fast mode, the normal advance path is disabled —
        // they must explicitly exit fast mode (or use the fast-mode advance
        // route) to play matches.
        $game = Game::findOrFail($gameId);
        if ($game->isFastMode()) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        try {
            $result = $this->coordinator->advance($gameId);
        } catch (\Throwable $e) {
            Log::error('Matchday advance failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('show-game', $gameId)
                ->with('error', __('messages.advance_failed'));
        }

        // Concurrent advance — another request beat us to the flag.
        if (! $result) {
            return redirect()->route('show-game', $gameId);
        }

        return match ($result->type) {
            'live_match' => redirect()->route('game.live-match', [
                'gameId' => $gameId,
                'matchId' => $result->matchId,
            ]),
            'season_complete' => redirect()->route(
                $game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end',
                $gameId,
            ),
            'done' => redirect()->route('show-game', $gameId),
            'blocked' => $result->pendingAction && $result->pendingAction['route']
                ? redirect()->route($result->pendingAction['route'], $gameId)
                    ->with('warning', __('messages.action_required'))
                : redirect()->route('show-game', $gameId)
                    ->with('warning', __('messages.action_required')),
        };
    }
}
