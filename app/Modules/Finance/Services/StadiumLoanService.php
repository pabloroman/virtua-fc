<?php

namespace App\Modules\Finance\Services;

use App\Models\ClubProfile;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameStadiumProject;
use App\Models\StadiumLoan;
use App\Models\TeamReputation;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Stadium\Enums\StadiumLoanStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use InvalidArgumentException;

class StadiumLoanService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Reputation-based ambition ceiling: the most a club of this tier can
     * borrow regardless of income. Returns 0 if the tier is below the
     * minimum required to take a stadium loan.
     */
    public function reputationLoanCap(string $reputationLevel): int
    {
        $caps = config('finances.stadium_loan.reputation_caps', []);

        return (int) ($caps[$reputationLevel] ?? 0);
    }

    /**
     * Affordability ceiling: the most a club can borrow given that the
     * year-1 instalment (highest in a flat-principal schedule) must fit
     * within max_debt_service_pct of projected operating revenue.
     *
     * year-1 instalment = principal × (1/term + interest_rate)
     * so       principal = maxAnnualPayment / (1/term + interest_rate)
     */
    public function affordabilityLoanCap(Game $game): int
    {
        $finances = $game->currentFinances;

        if (! $finances || $finances->projected_total_revenue <= 0) {
            return 0;
        }

        $maxPct = (float) config('finances.stadium_loan.max_debt_service_pct', 0.25);
        $termYears = (int) config('finances.stadium_loan.term_years', 10);
        $rateBps = (int) config('finances.stadium_loan.interest_rate_bps', 400);

        $maxAnnualPayment = $finances->projected_total_revenue * $maxPct;
        $firstYearRate = (1 / $termYears) + ($rateBps / 10000);

        if ($firstYearRate <= 0) {
            return 0;
        }

        $raw = (int) ($maxAnnualPayment / $firstYearRate);

        // Round down to the nearest €1M for a clean number in the UI.
        return (int) (floor($raw / 100_000_000) * 100_000_000);
    }

    /**
     * The binding loan ceiling for this game — the minimum of the
     * reputation cap (ambition) and the affordability cap (income).
     */
    public function maxLoanCap(Game $game): int
    {
        $level = TeamReputation::resolveLevel($game->id, $game->team_id);

        return min(
            $this->reputationLoanCap($level),
            $this->affordabilityLoanCap($game),
        );
    }

    /**
     * Projected operating revenue (cents/year) required so that
     * `affordabilityLoanCap` reaches at least `$principalCents`. Used by
     * the stadium UI to tell the user how much income they need to unlock
     * a target rebuild.
     */
    public function revenueRequiredForPrincipal(int $principalCents): int
    {
        $maxPct = (float) config('finances.stadium_loan.max_debt_service_pct', 0.25);
        $termYears = (int) config('finances.stadium_loan.term_years', 10);
        $rateBps = (int) config('finances.stadium_loan.interest_rate_bps', 400);

        $firstYearRate = (1 / $termYears) + ($rateBps / 10000);

        if ($firstYearRate <= 0 || $maxPct <= 0) {
            return 0;
        }

        return (int) ceil($principalCents * $firstYearRate / $maxPct);
    }

    /**
     * Create a stadium loan to fund a rebuild, stand-expansion, or UEFA
     * category upgrade project. Supplementary stands are cash-only.
     * The full principal is treated as drawn at commit time; repayments
     * begin from the next season.
     */
    public function request(Game $game, GameStadiumProject $project, int $principalCents): StadiumLoan
    {
        if (! in_array($project->type, [
            StadiumProjectType::Rebuild,
            StadiumProjectType::StandExpansion,
            StadiumProjectType::UefaUpgrade,
        ], true)) {
            throw new InvalidArgumentException('Stadium loans only fund rebuild, stand-expansion, or UEFA-upgrade projects.');
        }

        if ($principalCents <= 0) {
            throw new InvalidArgumentException('Loan principal must be positive.');
        }

        $cap = $this->maxLoanCap($game);
        if ($principalCents > $cap) {
            throw new InvalidArgumentException('messages.stadium_loan_exceeds_cap');
        }

        $loan = StadiumLoan::create([
            'game_id' => $game->id,
            'stadium_project_id' => $project->id,
            'principal_cents' => $principalCents,
            'term_years' => (int) config('finances.stadium_loan.term_years', 10),
            'interest_rate_bps' => (int) config('finances.stadium_loan.interest_rate_bps', 400),
            'remaining_principal_cents' => $principalCents,
            // First repayment is billed at the next season-close.
            'season_started' => (int) $game->season + 1,
            'status' => StadiumLoanStatus::Active,
        ]);

        $project->stadium_loan_id = $loan->id;
        $project->save();

        // Intentionally no FinancialTransaction here: the bank funds the
        // builder directly, so no cash reaches the club's books. Logging
        // the principal as income would inflate the transaction history.
        // Annual instalments are logged as expenses by billAnnualPayment().

        $this->notificationService->notifyStadiumLoanDrawn(
            $game,
            $loan->formatted_principal,
            $loan->term_years,
        );

        return $loan;
    }

    /**
     * Apply one year's instalment to the loan. Returns the amount billed.
     * Marks the loan repaid once remaining_principal hits zero. Caller is
     * responsible for deducting the returned amount from the budget.
     */
    public function billAnnualPayment(StadiumLoan $loan, Game $game): int
    {
        if (! $loan->isActive()) {
            return 0;
        }

        $payment = $loan->next_payment_cents;
        $interest = (int) round($loan->remaining_principal_cents * $loan->interest_rate_bps / 10000);
        $principal = $payment - $interest;

        $loan->remaining_principal_cents = max(0, $loan->remaining_principal_cents - $principal);
        if ($loan->remaining_principal_cents === 0) {
            $loan->status = StadiumLoanStatus::Repaid;
        }
        $loan->save();

        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_LOAN_REPAYMENT,
            amount: $payment,
            description: __('finances.tx_stadium_loan_instalment', [
                'amount' => \App\Support\Money::format($payment),
            ]),
            transactionDate: $game->current_date->toDateString(),
        );

        if ($loan->status === StadiumLoanStatus::Repaid) {
            $this->notificationService->notifyStadiumLoanRepaid($game, $loan->formatted_principal);
        }

        return $payment;
    }

    /**
     * Sum of the next instalment across every active stadium loan in the
     * game. Used by BudgetProjectionService to surface the upcoming season's
     * debt-service line.
     */
    public function activePaymentsForGame(Game $game): int
    {
        return (int) StadiumLoan::query()
            ->where('game_id', $game->id)
            ->where('status', StadiumLoanStatus::Active->value)
            ->get()
            ->sum(fn (StadiumLoan $loan) => $loan->next_payment_cents);
    }
}
