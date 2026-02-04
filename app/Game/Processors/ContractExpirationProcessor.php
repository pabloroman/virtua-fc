<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;

/**
 * Releases players whose contracts have expired.
 * Priority: 5 (runs early, before contract renewals are applied)
 *
 * Players with contract_until <= June 30 of the ending season are released.
 * For user's team: players become free agents (removed from squad)
 * For AI teams: handled automatically (removed or auto-renewed elsewhere)
 */
class ContractExpirationProcessor implements SeasonEndProcessor
{
    public function priority(): int
    {
        return 5; // Before ContractRenewalProcessor (6)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Season ends on June 30 of the season year
        // e.g., season "2024" ends June 30, 2025; season "2025" ends June 30, 2026
        $seasonYear = (int) $data->oldSeason;
        $expirationDate = Carbon::createFromDate($seasonYear + 1, 6, 30)->endOfDay();

        // Find all players in this game whose contracts have expired
        $expiredPlayers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->whereNotNull('contract_until')
            ->where('contract_until', '<=', $expirationDate)
            ->whereNull('pending_annual_wage') // Exclude players who renewed
            ->get();

        $releasedPlayers = [];

        foreach ($expiredPlayers as $player) {
            // Store info before releasing
            $releasedPlayers[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'teamId' => $player->team_id,
                'teamName' => $player->team?->name,
                'wasUserTeam' => $player->team_id === $game->team_id,
            ];

            // Delete the player from the game (contract expired = no longer playing)
            $player->delete();
        }

        // Store released players info in metadata
        return $data->setMetadata('expiredContracts', $releasedPlayers);
    }
}
