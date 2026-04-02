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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRemainingBatches implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function __construct(
        public string $gameId,
        public int $careerActionTicks,
    ) {
        $this->onQueue('gameplay');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function handle(MatchdayOrchestrator $orchestrator): void
    {
        // Prevent query log from accumulating SQL strings across batch iterations
        DB::disableQueryLog();
        DB::flushQueryLog();

        $startMemory = memory_get_usage(true);
        Log::info('[ProcessRemainingBatches] Starting', [
            'game_id' => $this->gameId,
            'memory_mb' => round($startMemory / 1024 / 1024, 2),
        ]);

        $game = Game::find($this->gameId);

        if (! $game || ! $game->isProcessingRemainingBatches()) {
            return;
        }

        $orchestrator->processRemainingBatches($game, $this->careerActionTicks);

        Log::info('[ProcessRemainingBatches] Completed', [
            'game_id' => $this->gameId,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'delta_mb' => round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)->update(['remaining_batches_processing_at' => null]);

        Log::error('Remaining batches processing failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
