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

        // Find players whose injury_until has passed. The user's squad
        // always has match-state rows so an INNER JOIN is correct.
        $recoveredPlayers = GamePlayer::with('matchState')
            ->joinMatchState()
            ->where('game_players.game_id', $game->id)
            ->where('game_players.team_id', $game->team_id)
            ->whereMatchStatNotNull('injury_until')
            ->whereMatchStat('injury_until', '<', $event->newDate->toDateString())
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
            // Capture the return date before clearInjury() wipes it. current_date is
            // forward-looking (the next match), which can be days past the real return
            // date — e.g. across the pre-season → first-league-match gap — so the
            // recovery notice must be dated to injury_until to stay consistent with the
            // "out until X" date the injury notice promised.
            $recoveredOn = $player->injury_until?->copy();

            $this->eligibilityService->clearInjury($player);

            if (! in_array($player->id, $recentNotificationPlayerIds)) {
                $this->notificationService->notifyRecovery($game, $player, $recoveredOn);
            }
        }
    }
}
