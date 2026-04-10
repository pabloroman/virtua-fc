<?php

namespace App\Modules\Transfer\Listeners;

use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\AITransferMarketService;

class ProcessTransferWindowClose
{
    public function __construct(
        private readonly AITransferMarketService $aiTransferMarketService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // Detect boundary crossing: previousDate was inside a window, newDate is outside.
        // This fires on the same matchday as NotifyTransferWindowClosed, ~1 week after
        // the "window closing" warning.
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        if (! $previousWindow || $newWindow !== null) {
            return;
        }

        $game = $event->game;

        $alreadyProcessed = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_AI_TRANSFER_ACTIVITY)
            ->whereJsonContains('metadata->window', $previousWindow->value)
            ->where('game_date', '>=', $event->previousDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        $this->aiTransferMarketService->processWindowClose($game, $previousWindow->value);
    }
}
