<?php

namespace App\Modules\Match\Jobs;

use App\Models\Game;
use App\Modules\Match\Services\MatchdayOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRemainingBatches implements ShouldQueue, ShouldBeUnique
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

    public function handle(MatchdayOrchestrator $orchestrator): void
    {
        $game = Game::find($this->gameId);

        if (! $game || ! $game->isProcessingAiBatches()) {
            return;
        }

        $ticks = $orchestrator->processRemainingBatches($game);

        $game->update(['ai_batches_processing_at' => null]);

        // Dispatch career actions if any ticks accumulated during background batches.
        // Delay by 5 seconds to avoid ShouldBeUnique conflict with the career actions
        // dispatched by the main matchday advance job. If the first job's unique lock
        // is still held, this dispatch is silently dropped — acceptable since these
        // ticks only affect AI team decisions and will self-correct on the next advance.
        if ($ticks > 0) {
            $updated = Game::where('id', $this->gameId)
                ->whereNull('career_actions_processing_at')
                ->update(['career_actions_processing_at' => now()]);

            if ($updated) {
                try {
                    ProcessCareerActions::dispatch($this->gameId, $ticks)
                        ->delay(now()->addSeconds(5));
                } catch (\Throwable $e) {
                    Game::where('id', $this->gameId)->update(['career_actions_processing_at' => null]);
                }
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)->update(['ai_batches_processing_at' => null]);

        Log::error('Remaining AI batches processing failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
