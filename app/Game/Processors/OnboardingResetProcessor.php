<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\Game;

/**
 * Resets the onboarding flag so the player must configure
 * their investment allocation for the upcoming season.
 */
class OnboardingResetProcessor implements SeasonEndProcessor
{
    public function priority(): int
    {
        return 110; // Last — after budget projections are ready
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $game->update(['needs_onboarding' => true]);

        return $data;
    }
}
