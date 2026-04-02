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
use Illuminate\Support\Facades\Log;

/**
 * Handles players whose contracts have expired.
 * Priority: 20 (runs early, before contract renewals are applied)
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
        $seasonYear = (int) $data->oldSeason;
        $expirationDate = Carbon::createFromDate($seasonYear + 1, 6, 30)->endOfDay();
        $veteranCutoff = PlayerAge::dateOfBirthCutoff(PlayerAge::PRIME_END + 1, $game->current_date);

        // Find all players with expired contracts — join players table for age calculation
        $expiredPlayers = GamePlayer::join('players', 'game_players.player_id', '=', 'players.id')
            ->where('game_players.game_id', $game->id)
            ->whereNotNull('game_players.team_id')
            ->whereNotNull('game_players.contract_until')
            ->where('game_players.contract_until', '<=', $expirationDate)
            ->whereNull('game_players.pending_annual_wage')
            ->select([
                'game_players.id',
                'game_players.team_id',
                'game_players.position',
                'players.date_of_birth',
            ])
            ->get();

        // Players with agreed outgoing pre-contracts — use keyed array for O(1) lookup
        $preContractPlayerIds = TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where(function ($query) {
                $query->whereNull('direction')
                    ->orWhere('direction', '!=', TransferOffer::DIRECTION_INCOMING);
            })
            ->pluck('game_player_id')
            ->flip()
            ->all();

        $releasedIds = [];
        $freeAgentIds = [];
        $autoRenewedIds = [];
        $newContractEnd = Carbon::createFromDate($seasonYear + 3, 6, 30);

        foreach ($expiredPlayers as $player) {
            if ($player->team_id === $game->team_id) {
                // User's team: release (except pre-contract departures)
                if (!isset($preContractPlayerIds[$player->id])) {
                    $releasedIds[] = $player->id;
                }
            } else {
                // AI teams: veterans (35+) have 50% chance of becoming free agents
                $isVeteran = $player->date_of_birth && Carbon::parse($player->date_of_birth)->lte($veteranCutoff);
                if ($isVeteran && mt_rand(1, 100) <= 50) {
                    $freeAgentIds[] = $player->id;
                } else {
                    $autoRenewedIds[] = $player->id;
                }
            }
        }

        // Batch operations
        if (!empty($releasedIds)) {
            GamePlayer::whereIn('id', $releasedIds)->delete();
        }
        if (!empty($freeAgentIds)) {
            GamePlayer::whereIn('id', $freeAgentIds)->update(['team_id' => null, 'number' => null]);
        }
        if (!empty($autoRenewedIds)) {
            GamePlayer::whereIn('id', $autoRenewedIds)->update(['contract_until' => $newContractEnd]);
        }

        Log::info('[ContractExpiration] Free agents created: ' . count($freeAgentIds) . ', released: ' . count($releasedIds) . ', auto-renewed: ' . count($autoRenewedIds));

        return $data;
    }
}
