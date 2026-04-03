<?php

namespace App\Modules\Squad\Listeners;

use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Squad\Services\SquadNumberService;
use App\Modules\Transfer\Enums\TransferWindowType;

class EnforceSquadRegistration
{
    public function __construct(
        private readonly SquadNumberService $squadNumberService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        // Only detect transfer window close boundary:
        // previousDate was inside a window, newDate is outside.
        $previousWindow = TransferWindowType::fromDate($event->previousDate);
        $newWindow = TransferWindowType::fromDate($event->newDate);

        if (! $previousWindow || $newWindow !== null) {
            return;
        }

        $game = $event->game;

        if (! $game->squad_registration_enabled) {
            return;
        }

        // Auto-assign numbers smartly, only add pending action if truly unresolvable
        $unresolvable = $this->squadNumberService->reassignNumbers($game);

        if ($unresolvable > 0) {
            $game->addPendingAction('squad_registration', 'game.squad.registration');
        }
    }
}
