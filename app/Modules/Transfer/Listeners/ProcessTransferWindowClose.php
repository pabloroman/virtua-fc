<?php

namespace App\Modules\Transfer\Listeners;

use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Services\AITransferMarketService;

class ProcessTransferWindowClose
{
    public function __construct(
        private readonly AITransferMarketService $aiTransferMarketService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $previousMonth = $event->previousDate->month;
        $newMonth = $event->newDate->month;

        // Detect if the date jumped across a window boundary
        $window = match (true) {
            in_array($previousMonth, [7, 8]) && ! in_array($newMonth, [7, 8]) => 'summer',
            $previousMonth === 1 && $newMonth !== 1 => 'winter',
            default => null,
        };

        if (! $window) {
            return;
        }

        $game = $event->game;

        // Already processed this window close?
        $alreadyProcessed = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_AI_TRANSFER_ACTIVITY)
            ->whereJsonContains('metadata->window', $window)
            ->where('game_date', '>=', $event->previousDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        $this->aiTransferMarketService->processWindowClose($game, $window);
    }
}
