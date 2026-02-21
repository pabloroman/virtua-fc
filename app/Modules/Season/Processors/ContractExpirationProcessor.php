<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;

/**
 * Handles players whose contracts have expired.
 * Priority: 5 (runs early, before contract renewals are applied)
 *
 * Players with contract_until <= June 30 of the ending season:
 * - User's team: released (removed from squad)
 * - AI teams: auto-renewed for 2 years to maintain roster stability
 */
class ContractExpirationProcessor implements SeasonEndProcessor
{
    public function priority(): int
    {
        return 5; // Before ContractRenewalProcessor (6)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Clean up any stale renewal negotiations
        app(ContractService::class)->expireStaleNegotiations($game);

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
        $autoRenewedPlayers = [];

        foreach ($expiredPlayers as $player) {
            if ($player->team_id === $game->team_id) {
                // User's team: release the player
                $releasedPlayers[] = [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'teamId' => $player->team_id,
                    'teamName' => $player->team->name,
                ];

                $player->delete();
            } else {
                // AI team: auto-renew for 2 years
                $newContractEnd = Carbon::createFromDate($seasonYear + 3, 6, 30);

                $player->update(['contract_until' => $newContractEnd]);

                $autoRenewedPlayers[] = [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'teamId' => $player->team_id,
                    'teamName' => $player->team->name,
                ];
            }
        }

        // Store metadata
        return $data->setMetadata('expiredContracts', $releasedPlayers)
            ->setMetadata('autoRenewedContracts', $autoRenewedPlayers);
    }
}
