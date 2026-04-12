<?php

namespace App\Modules\Squad\Listeners;

use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
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

        // injury_until lives on the satellite — join through it. The user's
        // squad always has match-state rows so an INNER JOIN is correct.
        $recoveredPlayerIds = GamePlayerMatchState::query()
            ->join('game_players', 'game_players.id', '=', 'game_player_match_state.game_player_id')
            ->where('game_players.game_id', $game->id)
            ->where('game_players.team_id', $game->team_id)
            ->whereNotNull('game_player_match_state.injury_until')
            ->where('game_player_match_state.injury_until', '<', $event->newDate->toDateString())
            ->pluck('game_player_match_state.game_player_id');

        $recoveredPlayers = GamePlayer::with('matchState')
            ->whereIn('id', $recoveredPlayerIds)
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
