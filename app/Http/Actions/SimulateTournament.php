<?php

namespace App\Http\Actions;

use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Models\Game;

class SimulateTournament
{
    public function __construct(
        private readonly MatchdayOrchestrator $orchestrator,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTournamentMode()) {
            return redirect()->route('show-game', $gameId);
        }

        for ($i = 0; $i < 500; $i++) {
            $result = $this->orchestrator->advance($game);

            if ($result->type === 'live_match') {
                return redirect()->route('game.live-match', [
                    'gameId' => $game->id,
                    'matchId' => $result->matchId,
                ]);
            }

            if (in_array($result->type, ['blocked', 'done', 'season_complete'])) {
                break;
            }

            $game->refresh()->setRelations([]);
        }

        return redirect()->route('game.tournament-end', $game->id);
    }
}
