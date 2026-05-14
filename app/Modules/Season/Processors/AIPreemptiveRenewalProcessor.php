<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\Loan;
use App\Modules\Player\PlayerAge;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
 *
 * Perf note: bypasses Eloquent hydration for the read pass (the universe is
 * ~2–5k AI players at season start; hydrating just to read 5 columns is
 * wasteful). Sorts each squad once and caches importance per player id, so
 * candidates within the same team are O(1) lookups instead of re-sorting.
 * Mirrors the raw-query bulk pattern in ContractExpirationProcessor.
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

        // Single lightweight scan: only the columns we need to filter and
        // rank, plus a join-derived flag for on-loan-out so we don't have to
        // eager-load a relation. Skips Eloquent hydration entirely on the
        // read side — saves ~1s on a fully-seeded universe.
        $rows = DB::table('game_players')
            ->leftJoin('loans', function ($join) {
                $join->on('loans.game_player_id', '=', 'game_players.id')
                    ->where('loans.status', '=', Loan::STATUS_ACTIVE);
            })
            ->where('game_players.game_id', $game->id)
            ->whereNotNull('game_players.team_id')
            ->where('game_players.team_id', '<>', $game->team_id)
            ->select(
                'game_players.id',
                'game_players.team_id',
                'game_players.overall_score',
                'game_players.contract_until',
                'game_players.date_of_birth',
                'game_players.pending_annual_wage',
                DB::raw('CASE WHEN loans.id IS NULL THEN 0 ELSE 1 END AS on_loan'),
            )
            ->get();

        $renewIds = [];
        $considered = 0;
        $veteranCutoffStr = $veteranCutoff->toDateString();
        $expirationDateStr = $expirationDate->toDateString();

        foreach ($rows->groupBy('team_id') as $teammates) {
            // Sort the whole squad once; build an id → importance map so
            // every candidate is an O(1) lookup. Mirrors the rank-based
            // formula in DispositionService::playerImportance, kept inline
            // to avoid that method's per-call re-sort.
            $sorted = $teammates->sortByDesc('overall_score')->values();
            $total = $sorted->count();
            $importanceById = [];
            foreach ($sorted as $rank => $row) {
                $importanceById[$row->id] = $total > 1
                    ? 1.0 - ($rank / ($total - 1))
                    : 0.5;
            }

            foreach ($teammates as $row) {
                if (!$this->isRenewalCandidate($row, $expirationDateStr, $veteranCutoffStr)) {
                    continue;
                }

                $considered++;
                $chance = $this->retentionChance($importanceById[$row->id] ?? 0.0);

                if (mt_rand(1, 100) <= $chance) {
                    $renewIds[] = $row->id;
                }
            }
        }

        if (!empty($renewIds)) {
            DB::table('game_players')
                ->whereIn('id', $renewIds)
                ->update(['contract_until' => $newContractEnd->toDateString()]);
        }

        Log::info('[AIPreemptiveRenewal] considered=' . $considered . ' renewed=' . count($renewIds));

        return $data->setMetadata('aiPreemptiveRenewals', [
            'considered' => $considered,
            'renewed' => count($renewIds),
        ]);
    }

    /**
     * Row comes from a raw DB query — fields are strings/scalars, not Carbon
     * or null-cast attributes. Compare as date strings (PostgreSQL emits
     * ISO-8601 from `date` columns; lexicographic order matches calendar
     * order).
     */
    private function isRenewalCandidate(object $row, string $expirationDateStr, string $veteranCutoffStr): bool
    {
        if ($row->contract_until === null || $row->contract_until > $expirationDateStr) {
            return false;
        }
        if ($row->pending_annual_wage !== null) {
            return false;
        }
        if ($row->on_loan) {
            return false;
        }
        // Veterans (date_of_birth on or before the cutoff) keep the existing
        // season-end coin flip; the AI doesn't preemptively decide on them.
        if ($row->date_of_birth === null || $row->date_of_birth <= $veteranCutoffStr) {
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
