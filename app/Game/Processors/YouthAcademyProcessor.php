<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\NotificationService;
use App\Game\Services\YouthAcademyService;
use App\Models\AcademyPlayer;
use App\Models\Game;

/**
 * Handles academy cleanup at season end:
 * 1. Develop loaned players (full season at 1.5x rate)
 * 2. Return loaned players to academy
 * 3. Add academy_evaluation pending action if players exist
 *
 * New batch generation is handled by the SeasonStarted event listener.
 */
class YouthAcademyProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
        private readonly NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 55;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // 1. Develop loaned players at 1.5x rate before returning
        $this->youthAcademyService->developLoanedPlayers($game);

        // 2. Return loaned players to academy
        $returnedPlayers = $this->youthAcademyService->returnLoans($game);

        if ($returnedPlayers->isNotEmpty()) {
            $data->setMetadata('academy_loans_returned', $returnedPlayers->count());
        }

        // 3. Mark non-loaned players as needing evaluation
        $totalPlayers = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->count();

        if ($totalPlayers > 0) {
            AcademyPlayer::where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->where('is_on_loan', false)
                ->update(['evaluation_needed' => true]);

            $game->addPendingAction('academy_evaluation', 'game.squad.academy.evaluate');
            $this->notificationService->notifyAcademyEvaluation($game);
        }

        $data->setMetadata('academy_players_count', $totalPlayers);

        return $data;
    }
}
