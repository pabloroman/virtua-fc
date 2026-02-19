<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Notification\Services\NotificationService;

class SendCupTieNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(CupTieResolved $event): void
    {
        $game = $event->game;
        $cupTie = $event->cupTie;

        if (! $cupTie->involvesTeam($game->team_id)) {
            return;
        }

        $roundConfig = $cupTie->getRoundConfig();
        $roundName = $roundConfig->name ?? '';
        $competitionName = $event->competition->name ?? 'Cup';

        if ($event->winnerId === $game->team_id) {
            $this->notificationService->notifyCompetitionAdvancement(
                $game,
                $event->competition->id,
                $competitionName,
                __('cup.advanced_past_round', ['round' => $roundName]),
            );
        } else {
            $this->notificationService->notifyCompetitionElimination(
                $game,
                $event->competition->id,
                $competitionName,
                __('cup.eliminated_in_round', ['round' => $roundName]),
            );
        }
    }
}
