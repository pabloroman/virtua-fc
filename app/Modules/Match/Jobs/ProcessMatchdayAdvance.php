<?php

namespace App\Modules\Match\Jobs;

use App\Events\SeasonCompleted;
use App\Models\ActivationEvent;
use App\Models\Game;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMatchdayAdvance implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function __construct(
        public string $gameId,
    ) {
        $this->onQueue('gameplay');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function handle(MatchdayOrchestrator $orchestrator, ActivationTracker $activationTracker): void
    {
        $game = Game::find($this->gameId);

        if (! $game || ! $game->isAdvancingMatchday()) {
            return;
        }

        $result = $orchestrator->advance($game);

        // Dispatch SeasonCompleted event for season_complete/done results
        if (in_array($result->type, ['season_complete', 'done'])) {
            $game->refresh();
            event(new SeasonCompleted($game));
        }

        // Record activation events
        $game->refresh();
        $activationTracker->record($game->user_id, ActivationEvent::EVENT_FIRST_MATCH_PLAYED, $game->id, $game->game_mode);

        if ($game->current_matchday >= 5) {
            $activationTracker->record($game->user_id, ActivationEvent::EVENT_MATCHDAY_5_REACHED, $game->id, $game->game_mode);
        }

        // Store result and clear processing flag
        $game->update([
            'matchday_advance_result' => $result->toArray(),
            'matchday_advancing_at' => null,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)->update([
            'matchday_advancing_at' => null,
            'matchday_advance_result' => null,
        ]);

        Log::error('Matchday advance failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
