<?php

namespace App\Modules\Squad\Listeners;

use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Squad\Services\EligibilityService;

class CheckRecoveredPlayers
{
    public function __construct(
        private readonly EligibilityService $eligibilityService,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $game = $event->game;

        $recoveredPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotNull('injury_until')
            ->where('injury_until', '<', $event->newDate->toDateString())
            ->get();

        if ($recoveredPlayers->isEmpty()) {
            return;
        }

        $recentNotificationPlayerIds = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_PLAYER_RECOVERED)
            ->where('game_date', '>', $event->newDate->copy()->subDays(7))
            ->pluck('metadata')
            ->map(fn ($m) => $m['player_id'] ?? null)
            ->filter()
            ->toArray();

        foreach ($recoveredPlayers as $player) {
            $this->eligibilityService->clearInjury($player);

            if (! in_array($player->id, $recentNotificationPlayerIds)) {
                $this->notificationService->notifyRecovery($game, $player);
            }
        }
    }
}
