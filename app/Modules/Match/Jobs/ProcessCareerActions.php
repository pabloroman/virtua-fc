<?php

namespace App\Modules\Match\Jobs;

use App\Models\Game;
use App\Modules\Match\Services\CareerActionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCareerActions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $gameId,
        public int $ticks,
    ) {}

    public function handle(CareerActionProcessor $processor): void
    {
        $game = Game::find($this->gameId);

        if (! $game || ! $game->isProcessingCareerActions()) {
            return;
        }

        for ($i = 0; $i < $this->ticks; $i++) {
            $game->refresh()->setRelations([]);
            $processor->process($game);
        }

        $game->update(['career_actions_processing_at' => null]);
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)->update(['career_actions_processing_at' => null]);

        Log::error('Career actions processing failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
