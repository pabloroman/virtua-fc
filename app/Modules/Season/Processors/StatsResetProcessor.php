<?php

namespace App\Modules\Season\Processors;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\PlayerSuspension;
use Carbon\Carbon;

/**
 * Resets player and game stats for the new season.
 * Priority: 20 (runs second)
 */
class StatsResetProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 65;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Clear all competition-specific suspensions for this game's players
        PlayerSuspension::whereIn('game_player_id', function ($query) use ($game) {
            $query->select('id')->from('game_players')->where('game_id', $game->id);
        })->delete();

        // Reset every active player's match-state. Pool players have no
        // satellite row to reset — and they have no stats to reset either,
        // so this is correct.
        //
        // injury_until / injury_type are intentionally NOT in this reset:
        // they're date-driven and the day-to-day CheckRecoveredPlayers
        // listener clears them once current_date passes injury_until.
        // Wiping them here would silently heal long-term injuries whose
        // recovery extends into the new season.
        GamePlayerMatchState::bulkResetForGame($game->id, [
            'appearances' => 0,
            'goals' => 0,
            'own_goals' => 0,
            'assists' => 0,
            'yellow_cards' => 0,
            'red_cards' => 0,
            'goals_conceded' => 0,
            'clean_sheets' => 0,
            'season_appearances' => 0,
            'fitness' => 80,
            'morale' => 80,
        ]);

        // Mark all previous-season notifications as read so the new season starts clean
        GameNotification::where('game_id', $game->id)
            ->unread()
            ->update(['read_at' => now()]);

        $this->notifyCarriedOverInjuries($game, $data);

        return $data;
    }

    /**
     * Surface long-term injuries that carry into the new season in the
     * notification feed, so the manager isn't surprised when an unavailable
     * player shows up on matchday 1.
     */
    private function notifyCarriedOverInjuries(Game $game, SeasonTransitionData $data): void
    {
        $newSeasonStart = Carbon::createFromDate((int) $data->newSeason, 7, 1);

        $injuredPlayers = GamePlayer::with('matchState')
            ->joinMatchState()
            ->where('game_players.game_id', $game->id)
            ->where('game_players.team_id', $game->team_id)
            ->whereMatchStatNotNull('injury_until')
            ->whereMatchStat('injury_until', '>=', $newSeasonStart->toDateString())
            ->get();

        foreach ($injuredPlayers as $player) {
            $weeksOut = max(1, (int) ceil($newSeasonStart->diffInDays($player->injury_until, false) / 7));

            $this->notificationService->notifyInjury(
                $game,
                $player,
                $player->injury_type ?? '',
                $weeksOut,
                duringMatch: false,
            );
        }
    }
}
