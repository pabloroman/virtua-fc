<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Report\Services\TournamentSnapshotService;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameDeletionService;
use App\Models\Game;

class SimulateTournament
{
    public function __construct(
        private readonly MatchdayOrchestrator $orchestrator,
        private readonly ActivationTracker $activationTracker,
        private readonly TournamentSnapshotService $snapshotService,
        private readonly GameDeletionService $deletionService,
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

        $this->activationTracker->record($game->user_id, ActivationEvent::EVENT_TOURNAMENT_COMPLETED, $game->id, Game::MODE_TOURNAMENT);

        $summary = $this->snapshotService->createSnapshot($game);
        $this->deletionService->delete($game);

        return redirect()->route('tournament-summary.show', $summary->id);
    }
}
