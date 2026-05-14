<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\DispositionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Has AI clubs proactively extend the contracts of players they want to keep
 * before the Jan 1 pre-contract window opens. Without this, the pre-contract
 * pool is flooded with every AI player whose contract is technically expiring
 * at season end — the existing ContractExpirationProcessor doesn't fire until
 * June, so during Jan–May the user can shop the entire top flight on a free.
 *
 * Skips veterans (they keep the existing 50/50 retire-vs-renew coin flip at
 * season end). For non-veteran AI players, retention probability tapers by
 * squad importance, leaving a believable surplus of fringe / bench players
 * available on the pre-contract market while keeping stars off it most of
 * the time.
 *
 * Priority 45: after StandingsReset (40), well before SquadRegistration (109)
 * and TransferMarketSeed (111) so they see the post-renewal contract state.
 */
class AIPreemptiveRenewalProcessor implements SeasonProcessor
{
    /** Renewal chance (percent) when the player is in the top third of the squad by ability. */
    private const RETENTION_TOP_PERCENT = 80;

    /** Renewal chance for the middle third. */
    private const RETENTION_MID_PERCENT = 50;

    /** Renewal chance for the bottom third. */
    private const RETENTION_LOW_PERCENT = 15;

    private const TOP_IMPORTANCE_THRESHOLD = 0.70;
    private const MID_IMPORTANCE_THRESHOLD = 0.40;

    public function __construct(
        private readonly DispositionService $dispositionService,
    ) {}

    public function priority(): int
    {
        return 45;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $newSeasonYear = (int) $data->newSeason;

        // Players whose contract ends on or before the end of the new season
        // would otherwise be in the pre-contract pool during Jan–May.
        $expirationDate = Carbon::createFromDate($newSeasonYear + 1, 6, 30)->endOfDay();

        // Renewed contracts get the same +3 year extension the season-end
        // auto-renewal applies, anchored to the new season for determinism.
        $newContractEnd = Carbon::createFromDate($newSeasonYear + 3, 6, 30);

        $veteranCutoff = PlayerAge::dateOfBirthCutoff(
            PlayerAge::PRIME_END + 1,
            $game->current_date,
        );

        // Load every AI-team player once, grouped by team, so playerImportance
        // can rank against full-squad teammates without re-querying.
        $allAiPlayers = GamePlayer::query()
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '<>', $game->team_id)
            ->with('activeLoan')
            ->get();

        $renewIds = [];
        $considered = 0;

        foreach ($allAiPlayers->groupBy('team_id') as $teammates) {
            $candidates = $teammates->filter(
                fn (GamePlayer $p) => $this->isRenewalCandidate($p, $expirationDate, $veteranCutoff),
            );

            if ($candidates->isEmpty()) {
                continue;
            }

            foreach ($candidates as $player) {
                $considered++;
                $importance = $this->dispositionService->playerImportance($player, $teammates);
                $chance = $this->retentionChance($importance);

                if (mt_rand(1, 100) <= $chance) {
                    $renewIds[] = $player->id;
                }
            }
        }

        if (!empty($renewIds)) {
            GamePlayer::whereIn('id', $renewIds)
                ->update(['contract_until' => $newContractEnd]);
        }

        Log::info('[AIPreemptiveRenewal] considered=' . $considered . ' renewed=' . count($renewIds));

        return $data->setMetadata('aiPreemptiveRenewals', [
            'considered' => $considered,
            'renewed' => count($renewIds),
        ]);
    }

    private function isRenewalCandidate(
        GamePlayer $player,
        Carbon $expirationDate,
        Carbon $veteranCutoff,
    ): bool {
        if ($player->contract_until === null || $player->contract_until->gt($expirationDate)) {
            return false;
        }

        // Don't touch players the user is already in the middle of renewing.
        if ($player->pending_annual_wage !== null) {
            return false;
        }

        // Renewal authority on loaned-out players sits with the parent club's
        // own pipeline (and on-loan filial squads run separate flows).
        if ($player->isOnLoan()) {
            return false;
        }

        // Veterans (age > PRIME_END) keep the existing season-end coin flip,
        // simulating "club still deciding whether to keep the aging star."
        if ($player->date_of_birth === null || $player->date_of_birth->lte($veteranCutoff)) {
            return false;
        }

        return true;
    }

    private function retentionChance(float $importance): int
    {
        return match (true) {
            $importance >= self::TOP_IMPORTANCE_THRESHOLD => self::RETENTION_TOP_PERCENT,
            $importance >= self::MID_IMPORTANCE_THRESHOLD => self::RETENTION_MID_PERCENT,
            default                                       => self::RETENTION_LOW_PERCENT,
        };
    }
}
