<?php

namespace App\Modules\Season\Processors;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;

/**
 * Resets the new-season setup flag so the player must configure
 * their investment allocation for the upcoming season.
 * Also notifies about the summer transfer window being open.
 */
class NewSeasonResetProcessor implements SeasonProcessor
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 110; // Last — after budget projections are ready
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if ($data->isInitialSeason) {
            return $data;
        }

        $game->update(['needs_new_season_setup' => true]);

        $this->notificationService->notifyTransferWindowOpen($game, 'summer');

        return $data;
    }
}
