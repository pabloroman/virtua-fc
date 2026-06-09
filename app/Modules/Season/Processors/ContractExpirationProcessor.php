<?php

namespace App\Modules\Season\Processors;

use App\Modules\Player\PlayerAge;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TeamReputation;
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
 * - AI teams: veterans (> PRIME_END) have a tunable chance of non-renewal;
 *   non-veterans mostly auto-renew but a modest, tier-weighted minority run
 *   their contracts down to free agency instead. Non-renewed players become
 *   free agents (team_id = null) and may be signed by AI teams when the new
 *   season starts (AIFreeAgentSigningProcessor). Rates live in
 *   config/transfers.php (ai_contract_renewal).
 */
class ContractExpirationProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function priority(): int
    {
        return 20;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Clean up any stale renewal negotiations
        $this->contractService->expireStaleNegotiations($game);

        // Clean up unsigned free agents from the previous season
        GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->delete();

        // Season ends on June 30 of the season year
        $seasonYear = (int) $data->oldSeason;
        $expirationDate = Carbon::createFromDate($seasonYear + 1, 6, 30)->endOfDay();
        $veteranCutoff = PlayerAge::dateOfBirthCutoff(PlayerAge::PRIME_END + 1, $game->current_date);
        $newContractEnd = Carbon::createFromDate($seasonYear + 3, 6, 30);

        // Tunable AI renewal behaviour (config/transfers.php).
        $renewalConfig = config('transfers.ai_contract_renewal');
        $nonVeteranBase = (float) ($renewalConfig['non_veteran_non_renewal_base'] ?? 0.0);
        $nonVeteranAboveClubBonus = (float) ($renewalConfig['non_veteran_above_club_bonus'] ?? 0.0);
        $nonVeteranMax = (float) ($renewalConfig['non_veteran_non_renewal_max'] ?? 1.0);
        $veteranNonRenewal = (float) ($renewalConfig['veteran_non_renewal'] ?? 0.5);

        // AI non-veterans: most auto-renew so the renewal can re-derive each
        // player's wage from his current market value (renewAiContracts), not just
        // extend contract_until — AI pay used to stay frozen at the seeded rookie
        // wage for a whole career. A modest, tier-weighted share instead run their
        // contracts down to free agency (the non-renewal flip below), keeping a
        // realistic supply of quality free agents on the market each season. The
        // tier column feeds that flip's "too good for his club" weighting.
        $aiNonVeteranRows = DB::table('game_players')
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '<>', $game->team_id)
            ->whereNotNull('contract_until')
            ->where('contract_until', '<=', $expirationDate)
            ->whereNull('pending_annual_wage')
            ->where('date_of_birth', '>', $veteranCutoff->toDateString())
            ->select('id', 'team_id', 'market_value_cents', 'date_of_birth', 'tier')
            ->get();

        // AI veterans: narrow SELECT, then a tunable coin flip in PHP. Typically
        // <30 rows. Carries the same columns so the renewed half can be re-priced.
        $aiVeteranRows = DB::table('game_players')
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '<>', $game->team_id)
            ->whereNotNull('contract_until')
            ->where('contract_until', '<=', $expirationDate)
            ->whereNull('pending_annual_wage')
            ->where('date_of_birth', '<=', $veteranCutoff->toDateString())
            ->select('id', 'team_id', 'market_value_cents', 'date_of_birth')
            ->get();

        $veteranFreeAgentIds = [];
        $veteranAutoRenewedRows = [];
        foreach ($aiVeteranRows as $row) {
            if ($this->rollSucceeds($veteranNonRenewal)) {
                $veteranFreeAgentIds[] = $row->id;
            } else {
                $veteranAutoRenewedRows[] = $row;
            }
        }

        // AI non-veterans: a tier-weighted minority run their contracts down to
        // free agency instead of auto-renewing. The non-renewal chance rises with
        // how far the player's tier sits ABOVE his club's reputation, so the
        // departures skew toward genuinely attractive "too good for his club"
        // free agents rather than fringe squad filler.
        $clubReputationIndices = TeamReputation::resolveTierIndices(
            $game->id,
            $aiNonVeteranRows->pluck('team_id')->unique()->values()->all(),
        );
        $nonVeteranFreeAgentIds = [];
        $nonVeteranRenewedRows = [];
        foreach ($aiNonVeteranRows as $row) {
            $playerTierIndex = ((int) ($row->tier ?? 1)) - 1;
            $clubRepIndex = $clubReputationIndices[$row->team_id] ?? 0;
            $aboveClubSteps = max(0, $playerTierIndex - $clubRepIndex);
            $nonRenewalChance = min($nonVeteranMax, $nonVeteranBase + $aboveClubSteps * $nonVeteranAboveClubBonus);

            if ($this->rollSucceeds($nonRenewalChance)) {
                $nonVeteranFreeAgentIds[] = $row->id;
            } else {
                $nonVeteranRenewedRows[] = $row;
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
        $freeAgentIds = array_merge($userTeamFreeAgentIds, $veteranFreeAgentIds, $nonVeteranFreeAgentIds);
        if (!empty($freeAgentIds)) {
            // A release clause is a contract attribute: non-null ⟺ under
            // contract. Becoming a free agent nulls it (harmless no-op for saves
            // where the feature is off, since the column is already null).
            GamePlayer::whereIn('id', $freeAgentIds)->update(['team_id' => null, 'number' => null, 'release_clause' => null]);
        }

        // Auto-renew all AI keepers (the non-veterans that didn't run down +
        // the renewed half of the veteran coin flip), re-deriving each wage from
        // current market value.
        $aiRenewedRows = collect($nonVeteranRenewedRows)->concat($veteranAutoRenewedRows);
        $this->contractService->renewAiContracts(
            $aiRenewedRows,
            $newContractEnd->toDateString(),
            $game->current_date->toDateString(),
        );

        Log::info('[ContractExpiration] Free agents created: ' . count($freeAgentIds)
            . ', auto-renewed: ' . $aiRenewedRows->count());

        return $data;
    }

    /**
     * True if a probability `$p` (0.0–1.0) "hits" on a 1–100 roll. Zero or
     * negative probabilities never hit (no roll, no free agency), so callers
     * don't need to guard the chance themselves.
     */
    private function rollSucceeds(float $p): bool
    {
        return $p > 0 && mt_rand(1, 100) <= (int) round($p * 100);
    }
}
