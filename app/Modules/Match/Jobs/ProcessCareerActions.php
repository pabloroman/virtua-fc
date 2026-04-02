<?php

namespace App\Modules\Match\Jobs;

use App\Models\Game;
use App\Modules\Match\Services\CareerActionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessCareerActions implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 180;

    public function __construct(
        public string $gameId,
        public int $ticks,
    ) {
        $this->onQueue('gameplay');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function handle(CareerActionProcessor $processor): void
    {
        DB::disableQueryLog();
        DB::flushQueryLog();

        $startMemory = memory_get_usage(true);
        Log::info('[ProcessCareerActions] Starting', [
            'game_id' => $this->gameId,
            'ticks' => $this->ticks,
            'memory_mb' => round($startMemory / 1024 / 1024, 2),
        ]);

        $game = Game::find($this->gameId);

        if (! $game || ! $game->isProcessingCareerActions()) {
            return;
        }

        for ($i = 0; $i < $this->ticks; $i++) {
            if ($i > 0) {
                $game->refresh();
            }
            $processor->process($game);

            // Reclaim Eloquent circular references from models loaded during this tick
            // (loadTransferContext loads 1000+ players with eager-loaded relations per tick)
            gc_collect_cycles();

            Log::info('[ProcessCareerActions] Tick complete', [
                'game_id' => $this->gameId,
                'tick' => $i + 1,
                'of' => $this->ticks,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'delta_mb' => round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2),
            ]);
        }

        $game->update(['career_actions_processing_at' => null]);

        Log::info('[ProcessCareerActions] Completed', [
            'game_id' => $this->gameId,
            'ticks' => $this->ticks,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'delta_mb' => round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Game::where('id', $this->gameId)->update(['career_actions_processing_at' => null]);

        Log::error('Career actions processing failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
