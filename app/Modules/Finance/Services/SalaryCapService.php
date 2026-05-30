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
 * club's *recurring* revenue (`projected_total_revenue`), which deliberately
 * excludes one-time cash (carried surplus, player-sale proceeds). That is what
 * closes the free-signing exploit: hoarded cash can never lift the wage
 * ceiling, so the only way to afford a bigger wage bill is to grow recurring
 * income (league position, promotion, commercial growth, a bigger stadium).
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
        $revenue = $game->currentFinances?->projected_total_revenue
            ?? $this->budgetProjectionService->generateProjections($game)->projected_total_revenue;

        return (int) round($revenue * $this->capRatio($game));
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
     * Whether committing $newWage would keep the club within its cap.
     *
     * @param  int  $newWage    The wage being added (cents).
     * @param  int  $freedWage  Wage simultaneously freed by the same move
     *         (cents) — e.g. a renewal replaces the player's current wage, so
     *         pass the player's effective wage to charge only the increase.
     */
    public function canCommitWage(Game $game, int $newWage, int $freedWage = 0): bool
    {
        return ($this->committedWageBill($game) - $freedWage + $newWage) <= $this->cap($game);
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
     */
    public function blockMessage(Game $game, string $playerName, int $newWage, int $freedWage = 0): string
    {
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
