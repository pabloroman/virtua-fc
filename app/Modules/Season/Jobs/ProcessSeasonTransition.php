<?php

namespace App\Modules\Season\Jobs;

use App\Events\SeasonStarted;
use App\Models\Game;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Season\Services\SeasonSetupPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSeasonTransition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $gameId,
    ) {
        $this->onQueue('setup');
    }

    public function handle(
        SeasonClosingPipeline $closingPipeline,
        SeasonSetupPipeline $setupPipeline,
    ): void {
        $game = Game::find($this->gameId);

        if (!$game || !$game->isTransitioningSeason()) {
            return;
        }

        // Phase 1: Close old season
        $data = $closingPipeline->run($game);

        // Advance game to the new season (after closing processors finish,
        // so they all see the old season when looking up simulated data)
        $game->refresh()->setRelations([]);
        $game->update(['season' => $data->newSeason]);

        // Phase 2: Set up new season
        $game->refresh()->setRelations([]);
        $setupPipeline->run($game, $data);

        // Finalize: set current date and clear transition flag
        $game->refresh()->setRelations([]);
        $firstMatch = $game->getFirstCompetitiveMatch();
        $fallbackDate = ((int) $game->season) . '-08-15';

        $game->update([
            'current_date' => $firstMatch?->scheduled_date ?? $fallbackDate,
            'season_transitioning_at' => null,
        ]);

        event(new SeasonStarted($game));
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Season transition failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
