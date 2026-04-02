<?php

namespace App\Modules\Season\Processors;

use App\Modules\Player\PlayerAge;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Carbon\Carbon;

/**
 * Handles players whose contracts have expired.
 * Priority: 5 (runs early, before contract renewals are applied)
 *
 * Players with contract_until <= June 30 of the ending season:
 * - User's team: released (removed from squad)
 * - AI teams: veterans (35+) have a 50% chance of non-renewal and become
 *   free agents (team_id = null). All others are auto-renewed.
 *   Free agents may be signed by AI teams when the new season starts
 *   (AITransferMarketService).
 */
class ContractExpirationProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 20;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Clean up any stale renewal negotiations
        app(ContractService::class)->expireStaleNegotiations($game);

        // Clean up unsigned free agents from the previous season
        GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->delete();

        // Season ends on June 30 of the season year
        // e.g., season "2024" ends June 30, 2025; season "2025" ends June 30, 2026
        $seasonYear = (int) $data->oldSeason;
        $expirationDate = Carbon::createFromDate($seasonYear + 1, 6, 30)->endOfDay();

        // Find all players in this game whose contracts have expired
        $expiredPlayers = GamePlayer::with(['player', 'team', 'game'])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->whereNotNull('contract_until')
            ->where('contract_until', '<=', $expirationDate)
            ->whereNull('pending_annual_wage') // Exclude players who renewed
            ->get();

        $releasedPlayers = [];
        $autoRenewedPlayers = [];
        $newFreeAgents = [];

        $releasedIds = [];
        $freeAgentIds = [];
        $autoRenewedIds = [];
        $newContractEnd = Carbon::createFromDate($seasonYear + 3, 6, 30);

        // Players with agreed outgoing pre-contracts will be handled by
        // PreContractTransferProcessor — do not delete them here.
        $preContractPlayerIds = TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where(function ($query) {
                $query->whereNull('direction')
                    ->orWhere('direction', '!=', TransferOffer::DIRECTION_INCOMING);
            })
            ->pluck('game_player_id')
            ->all();

        // Process user's team first (always release, except pre-contract departures)
        foreach ($expiredPlayers->where('team_id', $game->team_id) as $player) {
            if (in_array($player->id, $preContractPlayerIds)) {
                continue;
            }

            $releasedPlayers[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'teamId' => $player->team_id,
                'teamName' => $player->team->name,
            ];
            $releasedIds[] = $player->id;
        }

        // Process AI teams: veterans have 50% chance of non-renewal
        $aiExpiredPlayers = $expiredPlayers
            ->filter(fn ($p) => $p->team_id !== $game->team_id);

        foreach ($aiExpiredPlayers as $player) {
            $age = $player->age($game->current_date);
            $shouldRelease = PlayerAge::isVeteran($age) && mt_rand(1, 100) <= 50;

            if ($shouldRelease) {
                $newFreeAgents[] = [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'position' => $player->position,
                    'teamId' => $player->team_id,
                    'teamName' => $player->team->name,
                    'age' => $age,
                ];
                $freeAgentIds[] = $player->id;
            } else {
                $autoRenewedPlayers[] = [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'teamId' => $player->team_id,
                    'teamName' => $player->team->name,
                ];
                $autoRenewedIds[] = $player->id;
            }
        }

        // Batch operations instead of per-player queries
        if (!empty($releasedIds)) {
            GamePlayer::whereIn('id', $releasedIds)->delete();
        }
        if (!empty($freeAgentIds)) {
            GamePlayer::whereIn('id', $freeAgentIds)->update(['team_id' => null, 'number' => null]);
        }
        if (!empty($autoRenewedIds)) {
            GamePlayer::whereIn('id', $autoRenewedIds)->update(['contract_until' => $newContractEnd]);
        }

        return $data->setMetadata('expiredContracts', $releasedPlayers)
            ->setMetadata('autoRenewedContracts', $autoRenewedPlayers)
            ->setMetadata('aiContractDepartures', $newFreeAgents);
    }
}
