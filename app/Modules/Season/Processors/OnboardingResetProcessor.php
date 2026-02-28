<?php

namespace App\Modules\Season\Processors;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;

/**
 * Resets the onboarding flag so the player must configure
 * their investment allocation for the upcoming season.
 * Also notifies about the summer transfer window being open.
 */
class OnboardingResetProcessor implements SeasonEndProcessor
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
        $game->update(['needs_onboarding' => true]);

        $this->notificationService->notifyTransferWindowOpen($game, 'summer');

        return $data;
    }
}
