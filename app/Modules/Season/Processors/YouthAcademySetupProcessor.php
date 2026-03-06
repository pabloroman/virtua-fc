<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Notification\Services\NotificationService;
use App\Models\AcademyPlayer;
use App\Models\Game;

/**
 * Marks academy players as needing evaluation for the new season.
 * No-op for initial seasons (no academy players exist yet).
 *
 * New batch generation is handled by the SeasonStarted event listener.
 */
class YouthAcademySetupProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 55;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if ($data->isInitialSeason) {
            return $data;
        }

        $updated = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->update(['evaluation_needed' => true]);

        if ($updated > 0) {
            $game->addPendingAction('academy_evaluation', 'game.squad.academy.evaluate');
            $this->notificationService->notifyAcademyEvaluation($game);
        }

        $data->setMetadata('academy_players_count', $updated);

        return $data;
    }
}
