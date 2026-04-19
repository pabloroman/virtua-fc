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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles players whose contracts have expired.
 * Priority: 20 (runs early, before contract renewals are applied)
 *
 * Players with contract_until <= June 30 of the ending season:
 * - User's team: become free agents (team_id = null)
 * - AI teams: veterans (35+) have a 50% chance of non-renewal and become
 *   free agents (team_id = null). All others are auto-renewed.
 *   Free agents may be signed by AI teams when the new season starts
 *   (AIFreeAgentSigningProcessor).
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
        $newContractEnd = Carbon::createFromDate($seasonYear + 3, 6, 30);

        // AI non-veterans: auto-renew in a single set-based UPDATE. This is
        // ~80% of expired contracts — handling them purely in SQL avoids
        // hydrating hundreds of Eloquent models just to set one column.
        // Filter mirrors the old PHP branch: not user team, contract expired,
        // no pending wage, date_of_birth newer than the veteran cutoff.
        $aiAutoRenewedCount = DB::update(
            'UPDATE game_players gp
             SET contract_until = ?
             FROM players p
             WHERE gp.player_id = p.id
               AND gp.game_id = ?
               AND gp.team_id IS NOT NULL
               AND gp.team_id <> ?
               AND gp.contract_until IS NOT NULL
               AND gp.contract_until <= ?
               AND gp.pending_annual_wage IS NULL
               AND p.date_of_birth > ?',
            [
                $newContractEnd->toDateString(),
                $game->id,
                $game->team_id,
                $expirationDate->toDateTimeString(),
                $veteranCutoff->toDateString(),
            ]
        );

        // AI veterans: narrow SELECT of just the IDs, then 50% coin flip in
        // PHP. Typically <30 rows — no hydration, no wide JOIN.
        $aiVeteranIds = DB::table('game_players')
            ->join('players', 'game_players.player_id', '=', 'players.id')
            ->where('game_players.game_id', $game->id)
            ->whereNotNull('game_players.team_id')
            ->where('game_players.team_id', '<>', $game->team_id)
            ->whereNotNull('game_players.contract_until')
            ->where('game_players.contract_until', '<=', $expirationDate)
            ->whereNull('game_players.pending_annual_wage')
            ->where('players.date_of_birth', '<=', $veteranCutoff->toDateString())
            ->pluck('game_players.id');

        $veteranFreeAgentIds = [];
        $veteranAutoRenewedIds = [];
        foreach ($aiVeteranIds as $id) {
            if (mt_rand(1, 100) <= 50) {
                $veteranFreeAgentIds[] = $id;
            } else {
                $veteranAutoRenewedIds[] = $id;
            }
        }

        // User team expirations: no JOIN — age isn't used for the user's
        // own players. Just a narrow SELECT filtered by team_id.
        $userTeamExpiredIds = DB::table('game_players')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotNull('contract_until')
            ->where('contract_until', '<=', $expirationDate)
            ->whereNull('pending_annual_wage')
            ->pluck('id');

        // Players with agreed outgoing pre-contracts stay on their team
        // until the pre-contract transfer processor moves them — skip them.
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

        $userTeamFreeAgentIds = $userTeamExpiredIds
            ->reject(fn ($id) => isset($preContractPlayerIds[$id]))
            ->all();

        // Bulk operations
        $freeAgentIds = array_merge($userTeamFreeAgentIds, $veteranFreeAgentIds);
        if (!empty($freeAgentIds)) {
            GamePlayer::whereIn('id', $freeAgentIds)->update(['team_id' => null, 'number' => null]);
        }
        if (!empty($veteranAutoRenewedIds)) {
            GamePlayer::whereIn('id', $veteranAutoRenewedIds)->update(['contract_until' => $newContractEnd]);
        }

        Log::info('[ContractExpiration] Free agents created: ' . count($freeAgentIds)
            . ', auto-renewed: ' . ($aiAutoRenewedCount + count($veteranAutoRenewedIds)));

        return $data;
    }
}
