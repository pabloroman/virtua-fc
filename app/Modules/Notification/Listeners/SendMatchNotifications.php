<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Notification\Services\NotificationService;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;

class SendMatchNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
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

        // Track players who received a red card so we skip yellow accumulation for them
        $redCardPlayerIds = $events
            ->where('event_type', 'red_card')
            ->pluck('game_player_id')
            ->unique()
            ->all();

        $yellowCardNotified = [];

        foreach ($events as $matchEvent) {
            $player = $players->get($matchEvent->game_player_id);
            if (! $player || $player->team_id !== $userTeamId) {
                continue;
            }

            switch ($matchEvent->event_type) {
                case 'red_card':
                    $this->notifyRedCard($event, $player, $matchEvent);
                    break;
                case 'injury':
                    $this->notifyInjury($event, $player, $matchEvent);
                    break;
                case 'yellow_card':
                    // Skip yellow accumulation check if the player was also sent off with a red card
                    if (! in_array($player->id, $yellowCardNotified) && ! in_array($player->id, $redCardPlayerIds)) {
                        $this->notifyYellowCardAccumulation($event, $player);
                        $yellowCardNotified[] = $player->id;
                    }
                    break;
            }
        }
    }

    private function notifyRedCard(MatchFinalized $event, GamePlayer $player, MatchEvent $matchEvent): void
    {
        $competitionId = $event->competition->id ?? $event->match->competition_id;
        $suspension = PlayerSuspension::forPlayerInCompetition($player->id, $competitionId);

        if (! $suspension || $suspension->matches_remaining <= 0) {
            return;
        }

        $this->notificationService->notifySuspension(
            $event->game,
            $player,
            $suspension->matches_remaining,
            __('notifications.reason_red_card'),
            $event->competition->name,
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
        $competitionId = $event->competition->id ?? $event->match->competition_id;
        $suspension = PlayerSuspension::forPlayerInCompetition($player->id, $competitionId);

        if (! $suspension || $suspension->matches_remaining <= 0) {
            return;
        }

        $this->notificationService->notifySuspension(
            $event->game,
            $player,
            $suspension->matches_remaining,
            __('notifications.reason_yellow_accumulation'),
            $event->competition->name,
        );
    }
}
