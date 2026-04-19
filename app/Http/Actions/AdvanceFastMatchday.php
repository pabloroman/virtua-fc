<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
use Illuminate\Support\Facades\Log;

class AdvanceFastMatchday
{
    public function __construct(
        private readonly MatchdayAdvanceCoordinator $coordinator,
    ) {}

    public function __invoke(string $gameId)
    {
        try {
            $result = $this->coordinator->advance($gameId, fastForward: true);
        } catch (\Throwable $e) {
            Log::error('Fast-mode matchday advance failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('game.fast-mode', $gameId)
                ->with('error', __('messages.advance_failed'));
        }

        if (! $result) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        $game = Game::findOrFail($gameId);

        return match ($result->type) {
            // Fast mode never returns live_match (the orchestrator finalizes
            // the user's match inline); included only to satisfy match
            // exhaustiveness. `blocked` only reaches here on the transient
            // career-actions-in-progress race, which ShowFastMode handles.
            'live_match', 'done', 'blocked' => redirect()->route('game.fast-mode', $gameId),
            'season_complete' => redirect()->route(
                $game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end',
                $gameId,
            ),
        };
    }
}
