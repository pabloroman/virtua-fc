<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Squad\Services\EligibilityService;
use App\Modules\Notification\Services\NotificationService;
use App\Models\GamePlayer;
use App\Models\MatchEvent;

class SendMatchNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EligibilityService $eligibilityService,
    ) {}

    public function handle(MatchFinalized $event): void
    {
        $events = MatchEvent::where('game_match_id', $event->match->id)
            ->whereIn('event_type', ['red_card', 'injury', 'yellow_card'])
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $userTeamId = $event->game->team_id;
        $playerIds = $events->pluck('game_player_id')->unique()->all();
        $players = GamePlayer::whereIn('id', $playerIds)->get()->keyBy('id');

        foreach ($events as $matchEvent) {
            $player = $players->get($matchEvent->game_player_id);
            if (! $player || $player->team_id !== $userTeamId) {
                continue;
            }

            match ($matchEvent->event_type) {
                'red_card' => $this->notifyRedCard($event, $player, $matchEvent),
                'injury' => $this->notifyInjury($event, $player, $matchEvent),
                'yellow_card' => $this->notifyYellowCardAccumulation($event, $player),
                default => null,
            };
        }
    }

    private function notifyRedCard(MatchFinalized $event, GamePlayer $player, MatchEvent $matchEvent): void
    {
        $isSecondYellow = $matchEvent->metadata['second_yellow'] ?? false;
        $suspensionMatches = $isSecondYellow ? 1 : 1;

        $this->notificationService->notifySuspension(
            $event->game,
            $player,
            $suspensionMatches,
            __('notifications.reason_red_card'),
        );
    }

    private function notifyInjury(MatchFinalized $event, GamePlayer $player, MatchEvent $matchEvent): void
    {
        $injuryType = $matchEvent->metadata['injury_type'] ?? 'Unknown injury';
        $weeksOut = $matchEvent->metadata['weeks_out'] ?? 2;

        $this->notificationService->notifyInjury($event->game, $player, $injuryType, $weeksOut);
    }

    private function notifyYellowCardAccumulation(MatchFinalized $event, GamePlayer $player): void
    {
        $suspension = $this->eligibilityService->checkYellowCardAccumulation($player);

        if ($suspension) {
            $this->notificationService->notifySuspension(
                $event->game,
                $player,
                $suspension,
                __('notifications.reason_yellow_accumulation'),
            );
        }
    }
}
