<?php

namespace App\Modules\Finance\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Support\Money;

/**
 * Squad salary cap — "Límite de Coste de Plantilla".
 *
 * Single source of truth for the wage ceiling. The cap is a fraction of the
 * club's *recurring* revenue (`projected_total_revenue`) plus a trailing
 * player-trading allowance (`projected_trading_allowance`). It deliberately
 * excludes one-time cash (carried surplus, a single window's sale proceeds):
 * a one-off sale can't lift the ceiling, which is what closes the free-signing
 * exploit. Only a *sustained* net-selling record (smoothed over several
 * seasons) raises the cap — mirroring how real squad-cost rules count net
 * player trading. The way to afford a bigger wage bill is to grow recurring
 * income (league position, promotion, commercial growth, a bigger stadium) or
 * to build a durable player-trading model.
 *
 * Wage commitments are gated at the point of signing/renewal (see the
 * wage-commitment Actions). Once a deal is agreed it is honoured even if it
 * tips the club over the cap — an agreed deal can't be unilaterally cancelled
 * — so the club simply enters the displayed "over the cap" state.
 */
class SalaryCapService
{
    /** Usage ratio at/above which the cap is shown as "approaching the limit". */
    private const WARNING_THRESHOLD = 0.85;

    public function __construct(
        private readonly BudgetProjectionService $budgetProjectionService,
    ) {}

    /**
     * The wage ceiling for the club's current season, in cents.
     *
     * Based on projected recurring revenue × the cap ratio. Reads the season's
     * GameFinances row lazily; if projections haven't been generated yet (rare
     * mid-season), it generates them — mirroring how ShowFinances bootstraps.
     */
    public function cap(Game $game): int
    {
        $finances = $game->currentFinances
            ?? $this->budgetProjectionService->generateProjections($game);

        $base = $finances->projected_total_revenue + ($finances->projected_trading_allowance ?? 0);

        return (int) round($base * $this->capRatio($game));
    }

    /**
     * Wage-cap room contributed by the club's trailing player-trading allowance
     * (plusvalías), in cents — how much higher the cap sits thanks to sustained
     * net player sales. Zero for net buyers or clubs without trading history.
     * Surfaced as the "+€X from plusvalías" line on the finances page.
     */
    public function tradingAllowanceRoom(Game $game): int
    {
        $finances = $game->currentFinances
            ?? $this->budgetProjectionService->generateProjections($game);

        return $this->capDelta($game, $finances->projected_trading_allowance ?? 0);
    }

    /**
     * How much the wage ceiling would move if recurring revenue changed by
     * `$recurringRevenueDelta` cents. Lets a UI show the cap impact of a new
     * income stream (e.g. a naming-rights offer) — "+€X wage room" — without
     * re-projecting the whole budget. Pure function of the cap ratio.
     */
    public function capDelta(Game $game, int $recurringRevenueDelta): int
    {
        return (int) round($recurringRevenueDelta * $this->capRatio($game));
    }

    /**
     * The total annual wage the club is *committed* to, in cents.
     *
     * Counts every player currently on the roster (including loaned-in players,
     * whose full wage the borrowing club pays — there is no loan subsidy) plus
     * any wages already agreed but not yet applied:
     *  - players with a pending renewal are counted at their pending wage;
     *  - agreed-but-uncompleted incoming signings (free agents, pre-contracts,
     *    transfers parked as AGREED) are counted at their offered wage.
     *
     * Counting agreed-but-pending commitments stops a manager from stacking
     * several signings/renewals in one window that each fit individually but
     * blow past the cap once they all apply.
     */
    public function committedWageBill(Game $game): int
    {
        $squadWages = (int) GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->selectRaw('COALESCE(SUM(COALESCE(pending_annual_wage, annual_wage)), 0) AS total')
            ->value('total');

        $agreedIncoming = (int) TransferOffer::query()
            ->where('game_id', $game->id)
            ->where('offering_team_id', $game->team_id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->whereIn('offer_type', [
                TransferOffer::TYPE_USER_BID,
                TransferOffer::TYPE_PRE_CONTRACT,
                // Loaned-in players are paid in full, so an agreed-but-pending
                // loan-in is a committed wage too (LoanService::requestLoanIn
                // stamps offered_wage with the player's annual_wage).
                TransferOffer::TYPE_LOAN_IN,
            ])
            ->sum('offered_wage');

        return $squadWages + $agreedIncoming;
    }

    /**
     * Cap room left, in cents (never negative).
     */
    public function remainingRoom(Game $game): int
    {
        return max(0, $this->cap($game) - $this->committedWageBill($game));
    }

    /**
     * Committed wages as a fraction of the cap (0.0+, can exceed 1.0 if over).
     */
    public function usageRatio(Game $game): float
    {
        $cap = $this->cap($game);

        return $cap > 0 ? $this->committedWageBill($game) / $cap : 0.0;
    }

    /**
     * UI status: 'healthy' | 'warning' | 'over'.
     */
    public function status(Game $game): string
    {
        $ratio = $this->usageRatio($game);

        return match (true) {
            $ratio > 1.0 => 'over',
            $ratio >= self::WARNING_THRESHOLD => 'warning',
            default => 'healthy',
        };
    }

    /**
     * Whether the club is currently over its cap — the transfer market is then
     * locked (see canCommitWage). This is also the unlock threshold: as soon as
     * the committed bill is back at/under the cap the lock lifts.
     */
    public function isOverCap(Game $game): bool
    {
        return $this->committedWageBill($game) > $this->cap($game);
    }

    /**
     * Whether committing $newWage would keep the club within its cap.
     *
     * While the club is already over the cap the market is frozen — no new wage
     * commitment of any kind passes until the bill is back under the cap. The
     * intended recovery path is selling players (which lowers committedWageBill),
     * never being forced to release them for free.
     *
     * @param  int  $newWage    The wage being added (cents).
     * @param  int  $freedWage  Wage simultaneously freed by the same move
     *         (cents) — e.g. a renewal replaces the player's current wage, so
     *         pass the player's effective wage to charge only the increase.
     */
    public function canCommitWage(Game $game, int $newWage, int $freedWage = 0): bool
    {
        $committed = $this->committedWageBill($game);
        $cap = $this->cap($game);

        if ($committed > $cap) {
            return false;
        }

        return ($committed - $freedWage + $newWage) <= $cap;
    }

    /**
     * The wage that a player currently contributes to the bill — their pending
     * (agreed-but-unapplied) wage if any, otherwise their active wage. Used as
     * the `freedWage` when renegotiating an existing contract.
     */
    public function effectiveWageFor(GamePlayer $player): int
    {
        return $player->pending_annual_wage ?? $player->annual_wage ?? 0;
    }

    /**
     * Localised "this would breach the cap" message for blocked signings.
     *
     * Two cases: if the club is already over the cap the market is locked, so we
     * return the "sell players to get back under your limit" message; otherwise
     * the move itself would tip the club over, so we spell out the shortfall.
     */
    public function blockMessage(Game $game, string $playerName, int $newWage, int $freedWage = 0): string
    {
        if ($this->isOverCap($game)) {
            return __('messages.salary_cap_locked');
        }

        $projectedBill = $this->committedWageBill($game) - $freedWage + $newWage;
        $cap = $this->cap($game);

        return __('messages.signing_exceeds_salary_cap', [
            'player' => $playerName,
            'wage' => Money::format($newWage),
            'total' => Money::format($projectedBill),
            'cap' => Money::format($cap),
            'shortfall' => Money::format(max(0, $projectedBill - $cap)),
        ]);
    }

    /**
     * The configured cap ratio for this club. A scalar applies to every club;
     * an array keyed by reputation level lets the ratio vary by club stature.
     */
    private function capRatio(Game $game): float
    {
        $ratio = config('finances.wage_cap_ratio', 0.70);

        if (is_array($ratio)) {
            $reputation = TeamReputation::resolveLevel($game->id, $game->team_id);

            return (float) ($ratio[$reputation] ?? 0.70);
        }

        return (float) $ratio;
    }
}
