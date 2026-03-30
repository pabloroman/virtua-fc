<?php

namespace App\Modules\Transfer\Listeners;

use App\Models\GameMatch;
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
        // current_date is forward-looking: newDate is the upcoming match.
        $windowType = TransferWindowType::fromDate($event->newDate);

        if (! $windowType) {
            return;
        }

        $game = $event->game;

        // Look ahead: if the match after the upcoming one is outside the window,
        // then the upcoming match is the last one before the window closes.
        $nextMatch = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->where('scheduled_date', '>', $event->newDate->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if (! $nextMatch) {
            return;
        }

        if ($windowType->containsMonth($nextMatch->scheduled_date->month)) {
            return;
        }

        // Already processed this window close?
        $alreadyProcessed = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_AI_TRANSFER_ACTIVITY)
            ->whereJsonContains('metadata->window', $windowType->value)
            ->where('game_date', '>=', $event->newDate->copy()->startOfMonth())
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        $this->aiTransferMarketService->processWindowClose($game, $windowType->value);
    }
}
