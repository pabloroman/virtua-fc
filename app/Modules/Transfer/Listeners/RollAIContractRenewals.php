<?php

namespace App\Modules\Transfer\Listeners;

use App\Models\Game;
use App\Models\Loan;
use App\Models\TransferOffer;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Player\PlayerAge;
use App\Modules\Transfer\Services\ContractService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-matchday roll: AI clubs proactively extend the contracts of players
 * they want to keep. Without this, the pre-contract pool would be flooded
 * with every AI player whose contract expires at season end — the existing
 * ContractExpirationProcessor doesn't fire until June, so during Jan–May
 * the user can shop the entire top flight on a free.
 *
 * Distributes the renewal work across the season instead of doing it in one
 * batch at setup. By Jan 1 (~20 ticks in) roughly half the high-importance
 * bucket is renewed; the rest of each tier remains shoppable. Mid-window the
 * user still encounters a healthy supply of pre-contractable players from
 * each tier, and the pool gradually drains as the window progresses — which
 * matches how clubs really keep extending stars throughout the year to avoid
 * the Bosman, without making the January free market dry up entirely.
 *
 * Skips veterans (they keep the existing 50/50 retire-vs-renew coin flip at
 * season end), loaned-out players (renewal authority sits with the parent
 * pipeline), and players who already have a pending/agreed pre-contract
 * offer from another club so the AI can't rug-pull a user mid-negotiation.
 */
class RollAIContractRenewals
{
    /**
     * Per-tick renewal probability in permille (parts per 1000).
     *
     * Calibrated against ~20 ticks Aug→Dec so cumulative chance by the Jan 1
     * pre-contract window lands at roughly ~50% / ~22% / ~4% renewed for
     * top / mid / low importance — leaving a healthy free-agent pool to shop
     * in January. Over the full ~38-tick season the rates converge to
     * ~74% / ~37% / ~7%, with the remainder handled by the existing
     * season-end ContractExpirationProcessor coin flip.
     */
    private const ROLL_TOP_PERMILLE = 35;
    private const ROLL_MID_PERMILLE = 12;
    private const ROLL_LOW_PERMILLE = 2;

    private const TOP_IMPORTANCE_THRESHOLD = 0.70;
    private const MID_IMPORTANCE_THRESHOLD = 0.40;

    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $this->roll($event->game);
    }

    /**
     * Public for tests so the per-tick probability can be sampled directly
     * without standing up the event dispatcher.
     *
     * @return array{considered: int, renewed: int}
     */
    public function roll(Game $game): array
    {
        $seasonEndDate = $game->getSeasonEndDate();
        $expirationDateStr = $seasonEndDate->copy()->endOfDay()->toDateString();
        $newContractEnd = Carbon::createFromDate(((int) $game->season) + 3, 6, 30);
        $veteranCutoffStr = PlayerAge::dateOfBirthCutoff(
            PlayerAge::PRIME_END + 1,
            $game->current_date,
        )->toDateString();

        // Narrow the squad-scan to teams that actually have at least one
        // eligible expiring player. Most ticks across most AI clubs will
        // find nothing to do; the distinct-teams filter keeps the per-tick
        // cost bounded to "teams with work" instead of the full universe.
        $expiringTeamIds = DB::table('game_players')
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '<>', $game->team_id)
            ->where('contract_until', '<=', $expirationDateStr)
            ->where('date_of_birth', '>', $veteranCutoffStr)
            ->whereNull('pending_annual_wage')
            ->distinct()
            ->pluck('team_id');

        if ($expiringTeamIds->isEmpty()) {
            return ['considered' => 0, 'renewed' => 0];
        }

        $rows = DB::table('game_players')
            ->leftJoin('loans', function ($join) {
                $join->on('loans.game_player_id', '=', 'game_players.id')
                    ->where('loans.status', '=', Loan::STATUS_ACTIVE);
            })
            ->where('game_players.game_id', $game->id)
            ->whereIn('game_players.team_id', $expiringTeamIds)
            ->select(
                'game_players.id',
                'game_players.team_id',
                'game_players.overall_score',
                'game_players.contract_until',
                'game_players.date_of_birth',
                'game_players.market_value_cents',
                'game_players.pending_annual_wage',
                DB::raw('CASE WHEN loans.id IS NULL THEN 0 ELSE 1 END AS on_loan'),
            )
            ->get();

        // Players already mid-pre-contract with another club are off-limits.
        // Renewing them would rug-pull an active user (or AI) negotiation —
        // the existing pre-contract flow has earned priority. Fetch once and
        // hash for O(1) lookup.
        $blockedIds = DB::table('transfer_offers')
            ->where('game_id', $game->id)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
            ->pluck('game_player_id')
            ->flip()
            ->all();

        $renewIds = [];
        $considered = 0;

        foreach ($rows->groupBy('team_id') as $teammates) {
            $sorted = $teammates->sortByDesc('overall_score')->values();
            $total = $sorted->count();
            $importanceById = [];
            foreach ($sorted as $rank => $row) {
                $importanceById[$row->id] = $total > 1
                    ? 1.0 - ($rank / ($total - 1))
                    : 0.5;
            }

            foreach ($teammates as $row) {
                if (!$this->isRollCandidate($row, $expirationDateStr, $veteranCutoffStr)) {
                    continue;
                }
                if (isset($blockedIds[$row->id])) {
                    continue;
                }

                $considered++;
                $rateMille = $this->ratePermille($importanceById[$row->id] ?? 0.0);

                if (random_int(1, 1000) <= $rateMille) {
                    $renewIds[] = $row->id;
                }
            }
        }

        if (!empty($renewIds)) {
            // Extend the contract AND re-derive the wage from current market
            // value — without this AI pay stays frozen at the seeded rookie wage
            // for the player's whole career while the user's wages track ability.
            $renewSet = collect($renewIds)->flip();
            $this->contractService->renewAiContracts(
                $rows->filter(fn ($row) => $renewSet->has($row->id))->values(),
                $newContractEnd->toDateString(),
                $game->current_date->toDateString(),
            );
        }

        return ['considered' => $considered, 'renewed' => count($renewIds)];
    }

    private function isRollCandidate(object $row, string $expirationDateStr, string $veteranCutoffStr): bool
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
        if ($row->date_of_birth === null || $row->date_of_birth <= $veteranCutoffStr) {
            return false;
        }

        return true;
    }

    private function ratePermille(float $importance): int
    {
        return match (true) {
            $importance >= self::TOP_IMPORTANCE_THRESHOLD => self::ROLL_TOP_PERMILLE,
            $importance >= self::MID_IMPORTANCE_THRESHOLD => self::ROLL_MID_PERMILLE,
            default                                       => self::ROLL_LOW_PERMILLE,
        };
    }
}
