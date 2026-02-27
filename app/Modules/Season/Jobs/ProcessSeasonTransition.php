<?php

namespace App\Modules\Season\Jobs;

use App\Events\SeasonStarted;
use App\Models\Game;
use App\Modules\Season\Services\SeasonEndPipeline;
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

    public function __construct(
        public string $gameId,
    ) {}

    public function handle(SeasonEndPipeline $pipeline): void
    {
        $game = Game::find($this->gameId);

        if (!$game || !$game->isTransitioningSeason()) {
            return;
        }

        // Run the full season-end pipeline
        $pipeline->run($game);

        // Set current date to the first match of the new season
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
