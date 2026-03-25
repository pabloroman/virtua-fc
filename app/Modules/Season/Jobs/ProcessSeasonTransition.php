<?php

namespace App\Modules\Season\Jobs;

use App\Events\SeasonStarted;
use App\Models\Game;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Season\Services\SeasonSetupPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSeasonTransition implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public string $gameId,
    ) {
        $this->onQueue('setup');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function handle(
        SeasonClosingPipeline $closingPipeline,
        SeasonSetupPipeline $setupPipeline,
    ): void {
        $game = Game::find($this->gameId);

        if (!$game || !$game->isTransitioningSeason()) {
            return;
        }

        // Restore checkpoint state for crash recovery
        $lastStep = $game->season_transition_step ?? -1;
        $restoredData = $this->restoreTransitionData($game);
        $closingProcessorCount = count($closingPipeline->getProcessors());

        // Phase 1: Close old season (skip if fully completed in a previous run)
        if ($lastStep < $closingProcessorCount - 1) {
            $data = $closingPipeline->run($game, $lastStep, $restoredData);

            $game->refresh()->setRelations([]);
        } else {
            $data = $restoredData ?? new SeasonTransitionData(
                oldSeason: $game->season,
                newSeason: $game->season,
                competitionId: $game->competition_id,
            );
        }

        // Advance game to the new season (after closing processors finish,
        // so they all see the old season when looking up simulated data)
        $game->refresh()->setRelations([]);
        $game->update(['season' => $data->newSeason]);

        // Phase 2: Set up new season
        if (!isset($refreshedForSetup)) {
            $game->refresh()->setRelations([]);
        }
        $setupPipeline->run($game, $data, $closingProcessorCount, $lastStep);

        // Finalize: set current date and clear transition flag + checkpoint
        $game->refresh()->setRelations([]);
        $firstMatch = $game->getFirstCompetitiveMatch();
        $fallbackDate = ((int) $game->season) . '-08-15';

        $game->update([
            'current_date' => $firstMatch?->scheduled_date ?? $fallbackDate,
            'season_transitioning_at' => null,
            'season_transition_step' => null,
            'season_transition_data' => null,
        ]);

        event(new SeasonStarted($game));
    }

    /**
     * Restore SeasonTransitionData from the checkpoint JSON, if available.
     */
    private function restoreTransitionData(Game $game): ?SeasonTransitionData
    {
        $stored = $game->season_transition_data;

        if ($stored === null) {
            return null;
        }

        return new SeasonTransitionData(
            oldSeason: $stored['oldSeason'] ?? '',
            newSeason: $stored['newSeason'] ?? '',
            competitionId: $stored['competitionId'] ?? $game->competition_id,
            playerChanges: $stored['playerChanges'] ?? [],
            metadata: $stored['metadata'] ?? [],
        );
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Season transition failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
