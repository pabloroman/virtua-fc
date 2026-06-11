<?php

namespace App\Modules\Finance\Services;

use App\Models\BudgetLoan;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Squad\Services\SquadService;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use App\Modules\Finance\Services\StadiumLoanService;
use Carbon\Carbon;

class BudgetProjectionService
{
    public function __construct(
        private readonly SquadService $squadService,
        private readonly MatchAttendanceService $matchAttendanceService,
        private readonly SeasonTicketPricingService $seasonTicketPricingService,
        private readonly StadiumLoanService $stadiumLoanService,
        private readonly NamingRightsService $namingRightsService,
    ) {}
    /**
     * UEFA / RFEF solidarity funds by competition tier (in cents).
     * Segunda receives the full €1M pool; Primera RFEF gets a trimmed €250K share
     * that matches the division's real-world distribution.
     */
    private const SOLIDARITY_FUNDS_BY_TIER = [
        2 => 100_000_000, // €1M — Segunda
        3 =>  25_000_000, // €250K — Primera RFEF
    ];

    /**
     * Minimum transfer budget guaranteed after mandatory infrastructure, by competition tier (in cents).
     * Primera RFEF (tier 3) operates on a tighter floor that matches the division's cash reality.
     */
    private const MINIMUM_TRANSFER_BUDGET_BY_TIER = [
        1 => 100_000_000, // €1M — La Liga
        2 => 100_000_000, // €1M — Segunda
        3 =>  20_000_000, // €200K — Primera RFEF
    ];

    private const MINIMUM_TRANSFER_BUDGET_DEFAULT = 100_000_000; // €1M in cents

    /**
     * Baseline public subsidy granted every season by competition tier (in cents).
     * Applied on top of the gap-fill logic: a tier-3 club always receives at
     * least €250K in town/regional subsidies, and additional top-up kicks in
     * if the club still can't cover its infrastructure + transfer minimum.
     */
    private const SUBSIDY_BASELINE_BY_TIER = [
        3 => 25_000_000, // €250K — Primera RFEF
    ];

    /**
     * Maximum stadium seats used for commercial revenue calculation.
     * Prevents oversized stadiums from generating disproportionate commercial income.
     */
    private const MAX_COMMERCIAL_SEATS = 80_000;

    /**
     * Generate season projections for a game.
     * Called at the start of each season during pre-season.
     *
     * When $freshClub is true, the projection treats the team as if this were
     * its first season in the game: no carry-overs, no loan repayments, and
     * commercial revenue uses the stadium-based baseline instead of the prior
     * season's actuals. This is set during a Pro Manager team switch — the
     * previous season's finances belong to the old club and must not follow
     * the manager. See ApplyPendingTeamSwitchProcessor and
     * BudgetProjectionProcessor for the signal flow.
     */
    public function generateProjections(Game $game, bool $freshClub = false): GameFinances
    {
        // Get user's team and league
        $team = $game->team;
        $league = $game->competition;

        // Calculate squad strengths for all teams in the league
        $teamStrengths = $this->squadService->calculateLeagueStrengths($game, $league);

        // Get user's projected position
        $projectedPosition = $this->squadService->getProjectedPosition($team->id, $teamStrengths);

        // Calculate projected revenues
        $projectedTvRevenue = $this->calculateTvRevenue($projectedPosition, $league);
        $projectedMatchdayRevenue = $this->calculateMatchdayRevenue($team, $game);
        $projectedSolidarityFundsRevenue = self::SOLIDARITY_FUNDS_BY_TIER[$game->competition->tier] ?? 0;
        $projectedCommercialRevenue = $freshClub
            ? $this->firstSeasonCommercialRevenue($game, $team, $league)
            : $this->getBaseCommercialRevenue($game, $team, $league);
        $projectedSeasonTicketRevenue = $this->seasonTicketPricingService->getCurrent($game)?->total_revenue ?? 0;

        // Naming-rights income from an active stadium sponsorship, scaled to
        // the ground's expected fill. Zero when no deal is active.
        $projectedNamingRightsRevenue = $this->namingRightsService->projectedRevenueForGame($game);

        $projectedTotalRevenue = $projectedTvRevenue
            + $projectedMatchdayRevenue
            + $projectedSolidarityFundsRevenue
            + $projectedCommercialRevenue
            + $projectedSeasonTicketRevenue
            + $projectedNamingRightsRevenue;

        // Calculate projected wages
        $projectedWages = $this->calculateProjectedWages($game);

        // Calculate operating expenses based on club reputation
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $baseOperatingExpenses = config('finances.operating_expenses.' . $reputation, 700_000_000);
        $tierMultiplier = config('finances.operating_expense_tier_multiplier.' . $league->tier, 1.0);
        $projectedOperatingExpenses = (int) ($baseOperatingExpenses * $tierMultiplier);

        // Calculate projected surplus
        $projectedSurplus = $projectedTotalRevenue - $projectedWages - $projectedOperatingExpenses;

        // Get carried debt, surplus, and loan repayment from previous season.
        // After a Pro Manager team switch the previous season's GameFinances
        // and BudgetLoan rows belong to the old club, so we skip them.
        $carriedDebt = $freshClub ? 0 : $this->getCarriedDebt($game);
        $carriedSurplus = $freshClub ? 0 : $this->getCarriedSurplus($game);
        $previousLoanRepayment = $freshClub ? 0 : $this->getPreviousSeasonLoanRepayment($game);

        // Stadium debt service: next instalment of every active stadium
        // loan. Treated like previous_loan_repayment — reduces available
        // surplus so the user can't earmark it for transfers.
        $projectedStadiumDebtService = $this->stadiumLoanService->activePaymentsForGame($game);

        // Calculate public subsidy if needed to guarantee minimum viable budget
        $projectedSubsidyRevenue = $this->calculateSubsidy(
            $projectedSurplus, $carriedDebt, $carriedSurplus, $previousLoanRepayment + $projectedStadiumDebtService, $league->tier
        );
        if ($projectedSubsidyRevenue > 0) {
            $projectedTotalRevenue += $projectedSubsidyRevenue;
            $projectedSurplus += $projectedSubsidyRevenue;
        }

        // Trailing player-trading allowance ("plusvalías"): a smoothed measure of
        // the club's net selling over recent seasons that lifts the WAGE CAP only
        // — never the projected surplus/budget (the sale cash already reaches the
        // budget via carried surplus, so it is intentionally absent from
        // $projectedSurplus above). Zero on a fresh-club switch, since the trading
        // history belongs to the previous club. Computed against the final
        // recurring revenue so its guard matches the cap base.
        $projectedTradingAllowance = $freshClub
            ? 0
            : $this->trailingTradingAllowance($game, $projectedTotalRevenue);

        // Create or update finances record
        $finances = GameFinances::updateOrCreate(
            [
                'game_id' => $game->id,
                'season' => $game->season,
            ],
            [
                'projected_position' => $projectedPosition,
                'projected_tv_revenue' => $projectedTvRevenue,
                'projected_solidarity_funds_revenue' => $projectedSolidarityFundsRevenue,
                'projected_matchday_revenue' => $projectedMatchdayRevenue,
                'projected_season_ticket_revenue' => $projectedSeasonTicketRevenue,
                'projected_commercial_revenue' => $projectedCommercialRevenue,
                'projected_naming_rights_revenue' => $projectedNamingRightsRevenue,
                'projected_trading_allowance' => $projectedTradingAllowance,
                'projected_subsidy_revenue' => $projectedSubsidyRevenue,
                'projected_total_revenue' => $projectedTotalRevenue,
                'projected_wages' => $projectedWages,
                'projected_operating_expenses' => $projectedOperatingExpenses,
                'projected_surplus' => $projectedSurplus,
                'carried_debt' => $carriedDebt,
                'carried_surplus' => $carriedSurplus,
                'previous_loan_repayment' => $previousLoanRepayment,
                'projected_stadium_debt_service' => $projectedStadiumDebtService,
            ]
        );

        return $finances;
    }

    /**
     * Calculate TV revenue based on position and league.
     */
    public function calculateTvRevenue(int $position, Competition $league): int
    {
        $config = $league->getConfig();

        return $config->getTvRevenue($position);
    }

    /**
     * Project matchday revenue from walk-up fans and concessions only.
     * Season ticket holders are subtracted from the expected gate so we don't
     * double-count them — they already paid up front via the season ticket
     * sale, which lives in its own revenue line. This mirrors the settlement
     * formula (SeasonSettlementProcessor) exactly, so projection and actuals
     * use the same model.
     *
     * Formula:
     *   walkup_attendance = max(0, baseline_attendance − season_ticket_holders)
     *   revenue = walkup_attendance × perSeatMatchRate × totalHomeMatchCount
     *           × facilities_multiplier
     *
     * The per-seat rate (`stadium.revenue_per_seat.<reputation>`) stays
     * calibrated against real-world matchday revenue. A higher-fill pricing
     * preset sells more season tickets, shifting revenue from this bucket to
     * the season-ticket bucket — total roughly preserved.
     *
     * Runs at SeasonSetupPipeline priority 107 — after LeagueFixtureProcessor
     * (30) and ContinentalAndCupInitProcessor (106), so the fixture list for
     * the user's team is populated for leagues, Swiss-format competitions,
     * and round-1 cup ties. Later cup rounds are drawn dynamically as the
     * season progresses; treating them as upside rather than baseline mirrors
     * how real clubs project revenue conservatively.
     */
    public function calculateMatchdayRevenue(Team $team, Game $game): int
    {
        $factors = $this->matchdayProjectionFactors($team, $game);

        // No league home matches scheduled yet → no gate to project. Bail before
        // touching the ticketing services so this stays a cheap no-op pre-season.
        if ($factors['per_attendee_cents'] <= 0.0) {
            return 0;
        }

        $holders = $this->seasonTicketPricingService->soldSeasonTicketsForGame($game);

        // The chosen preset scales total demand (cheaper → bigger crowd), so the
        // walk-up volume left after holders follows. The per-seat gate rate stays
        // fixed, so taquilla revenue moves with attendance volume only.
        $demand = (int) round($factors['expected_attendance'] * $this->seasonTicketPricingService->currentOccupancyFactor($game));
        $walkupAttendance = max(0, $demand - $holders);

        return (int) ($walkupAttendance * $factors['per_attendee_cents']);
    }

    /**
     * The two inputs the matchday projection holds fixed while the user is
     * still choosing a season-ticket preset: the baseline expected attendance
     * and the per-attendee revenue factor (per-seat match rate × home matches
     * × facilities multiplier). The full projection reduces to
     * `max(0, expected_attendance − holders) × per_attendee_cents`, so the only
     * preset-dependent input is the holder count.
     *
     * The stadium page hands these two numbers to the client so it can
     * recompute the walk-up taquilla figure live as the user toggles presets —
     * each preset's holder count already lives in client state, so no round
     * trip is needed. calculateMatchdayRevenue() applies the same formula
     * server-side; the two must stay in lockstep. Returns a zero factor when
     * there are no league home matches yet, mirroring the old early return.
     *
     * @return array{expected_attendance: int, per_attendee_cents: float}
     */
    public function matchdayProjectionFactors(Team $team, Game $game): array
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);

        $homeMatchCounts = GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $team->id)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN competition_id = ? THEN 1 ELSE 0 END) AS league', [$game->competition_id])
            ->first();

        $leagueHomeMatchCount = (int) ($homeMatchCounts->league ?? 0);
        $totalHomeMatchCount = (int) ($homeMatchCounts->total ?? 0);

        if ($leagueHomeMatchCount === 0) {
            return ['expected_attendance' => 0, 'per_attendee_cents' => 0.0];
        }

        $perSeatSeasonRate = (int) config("stadium.revenue_per_seat.{$reputation}", 15_000);
        $perSeatMatchRate = $perSeatSeasonRate / $leagueHomeMatchCount;

        $investment = $game->currentInvestment;
        $facilitiesMultiplier = $investment
            ? GameInvestment::FACILITIES_MULTIPLIER[$investment->facilities_tier] ?? 1.0
            : 1.0;

        return [
            'expected_attendance' => $this->matchAttendanceService->projectBaselineForTeam($game->id, $team),
            'per_attendee_cents' => $perSeatMatchRate * $totalHomeMatchCount * $facilitiesMultiplier,
        ];
    }

    /**
     * Targeted refresh of the two ticketing-driven revenue lines after the
     * user changes the season-ticket preset (or the setup pipeline seeds the
     * default). Re-reads the persisted holder count and recomputes both the
     * season-ticket and matchday lines, folding the difference into projected
     * totals/surplus — without re-running the full (squad-strength) projection.
     *
     * One-directional: Stadium persists the pricing row, Finance reads it. The
     * ticketing service never calls back into Finance, so there is no cycle.
     * No-op when there's no finances row yet (the setup projection picks the
     * row up directly).
     */
    public function refreshTicketingProjection(Game $game): void
    {
        $finances = $game->currentFinances;
        if (! $finances) {
            return;
        }

        $newSeasonTicket = (int) ($this->seasonTicketPricingService->getCurrent($game)?->total_revenue ?? 0);
        $newMatchday = $this->calculateMatchdayRevenue($game->team, $game);

        $delta = ($newSeasonTicket - (int) $finances->projected_season_ticket_revenue)
            + ($newMatchday - (int) $finances->projected_matchday_revenue);

        // Season tickets are pre-paid, so projected and actual stay aligned.
        $finances->projected_season_ticket_revenue = $newSeasonTicket;
        $finances->actual_season_ticket_revenue = $newSeasonTicket;
        $finances->projected_matchday_revenue = $newMatchday;
        $finances->projected_total_revenue = (int) $finances->projected_total_revenue + $delta;
        $finances->projected_surplus = (int) $finances->projected_surplus + $delta;
        $finances->save();
    }

    /**
     * Calculate projected wages for the season.
     */
    public function calculateProjectedWages(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereDoesntHave('activeLoan', function ($q) use ($game) {
                $q->where('loan_team_id', $game->team_id);
            })
            ->sum('annual_wage');
    }

    /**
     * Trailing player-trading allowance in cents — the smoothed net player-
     * trading result (sales − purchases) over the last few COMPLETED seasons,
     * floored at zero (net buyers earn nothing, but pay no penalty) and capped
     * at a fraction of recurring revenue so trading can't dominate the cap.
     *
     * Only completed seasons count, so a club can't sell mid-window to inflate
     * its own ceiling that same window, and a one-off windfall is averaged down
     * — which is what keeps the free-signing exploit closed. Feeds the wage cap
     * only; see SalaryCapService::cap().
     *
     * Seasons with a NULL net_transfer_result are skipped: that marks a season
     * settled before this feature shipped (or not yet settled), as opposed to a
     * genuine break-even 0. Counting those back-dated rows as zeros would dilute
     * an existing save's average for up to `window_seasons` after upgrade.
     */
    private function trailingTradingAllowance(Game $game, int $recurringRevenue): int
    {
        $window = (int) config('finances.trading_allowance.window_seasons', 3);
        if ($window < 1) {
            return 0;
        }

        $nets = GameFinances::where('game_id', $game->id)
            ->where('season', '<', (int) $game->season)
            ->whereNotNull('net_transfer_result')
            ->orderByDesc('season')
            ->limit($window)
            ->pluck('net_transfer_result');

        if ($nets->isEmpty()) {
            return 0;
        }

        $weighted = max(0.0, (float) $nets->avg())
            * (float) config('finances.trading_allowance.weight', 1.0);

        $maxAllowance = $recurringRevenue
            * (float) config('finances.trading_allowance.max_fraction_of_recurring', 0.50);

        return (int) round(min($weighted, $maxAllowance));
    }

    /**
     * Calculate total squad market value.
     */
    public function calculateSquadValue(Game $game): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->sum('market_value_cents');
    }

    /**
     * Get carried debt from previous season.
     */
    public function getCarriedDebt(Game $game): int
    {
        $netPosition = $this->getPreviousSeasonNetPosition($game);

        return $netPosition < 0 ? abs($netPosition) : 0;
    }

    /**
     * Get carried surplus from previous season.
     */
    public function getCarriedSurplus(Game $game): int
    {
        $netPosition = $this->getPreviousSeasonNetPosition($game);

        return $netPosition > 0 ? $netPosition : 0;
    }

    /**
     * Calculate the net cash position at the end of the previous season.
     *
     * Net = actual_surplus + carried_surplus - carried_debt - infrastructure - transfer_purchases
     *
     * This accounts for ALL money flows: revenue performance (variance),
     * unspent transfer budget, and prior carry-overs.
     */
    private function getPreviousSeasonNetPosition(Game $game): int
    {
        $previousSeason = (int) $game->season - 1;

        $previousFinances = GameFinances::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if (!$previousFinances || $previousFinances->actual_surplus === 0 && $previousFinances->actual_total_revenue === 0) {
            return 0;
        }

        // Infrastructure committed during the previous season
        $previousInvestment = GameInvestment::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        $infrastructure = $previousInvestment?->total_infrastructure ?? 0;

        // Actual transfer spending (player purchases) during the previous season
        $seasonStart = Carbon::createFromDate($previousSeason, 7, 1);
        $seasonEnd = Carbon::createFromDate($previousSeason + 1, 6, 30);

        $transferSpending = FinancialTransaction::where('game_id', $game->id)
            ->whereBetween('transaction_date', [$seasonStart, $seasonEnd])
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_OUT)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        return $previousFinances->actual_surplus
            + $previousFinances->carried_surplus
            - $previousFinances->carried_debt
            - $infrastructure
            - $transferSpending;
    }

    /**
     * Get the previous season's budget loan repayment amount.
     * Shown as a separate deduction in the budget flow.
     */
    public function getPreviousSeasonLoanRepayment(Game $game): int
    {
        $previousSeason = (int) $game->season - 1;

        return (int) BudgetLoan::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->where('status', BudgetLoan::STATUS_REPAID)
            ->sum('repayment_amount');
    }

    /**
     * Get base commercial revenue for budget projections.
     * Season 2+: uses previous season's actual commercial revenue.
     * Season 1: calculates from stadium_seats × config rate.
     *
     * In every case the figure is floored at the club's brand baseline (see
     * commercialBrandFloor), so a marquee club never carries a sub-brand
     * commercial number — including existing saves whose prior-season actual
     * was settled before the brand floor existed.
     */
    private function getBaseCommercialRevenue(Game $game, Team $team, Competition $league): int|float
    {
        // Check for prior season actual commercial revenue
        $previousSeason = (int) $game->season - 1;
        $previousFinances = GameFinances::where('game_id', $game->id)
            ->where('season', $previousSeason)
            ->first();

        if ($previousFinances && $previousFinances->actual_commercial_revenue > 0) {
            return max(
                $previousFinances->actual_commercial_revenue,
                $this->commercialBrandFloor($game, $team, $league),
            );
        }

        return $this->firstSeasonCommercialRevenue($game, $team, $league);
    }

    /**
     * Commercial revenue baseline when there is no prior-season actual to
     * carry forward — used for season 1 of a new game and for the first
     * season at a new club after a Pro Manager team switch.
     */
    private function firstSeasonCommercialRevenue(Game $game, Team $team, Competition $league): int|float
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $seats = min($team->stadium_seats, self::MAX_COMMERCIAL_SEATS);

        $base = $seats * config("finances.commercial_per_seat.{$reputation}", 80_000);

        $stadiumDriven = $base * $this->commercialTierMultiplier($league);

        // A marquee club's commercial income is brand-led, not stadium-led, so
        // it can't fall below the reputation's brand floor (tier-scaled the same
        // way). max() keeps the larger of the two, so a big stadium still earns
        // its premium wherever it exceeds the floor.
        return max($stadiumDriven, $this->commercialBrandFloor($game, $team, $league));
    }

    /**
     * Brand-driven commercial floor for the club, in cents — the minimum
     * commercial income its reputation commands regardless of stadium size.
     * Returns 0 for reputation tiers with no brand premium ('established' and
     * below), making the floor a no-op for them. Tier-scaled by
     * commercial_tier_multiplier to mirror the per-seat commercial calculation.
     */
    private function commercialBrandFloor(Game $game, Team $team, Competition $league): int
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $floor = (int) config("finances.commercial_brand_floor.{$reputation}", 0);

        if ($floor === 0) {
            return 0;
        }

        return (int) ($floor * $this->commercialTierMultiplier($league));
    }

    /**
     * Tier multiplier applied to commercial income — scales a club's brand and
     * per-seat commercial down with the division (config-keyed by league tier).
     */
    private function commercialTierMultiplier(Competition $league): float
    {
        return (float) config("finances.commercial_tier_multiplier.{$league->tier}", 1.0);
    }

    /**
     * Calculate public subsidy (Subvenciones Públicas) to guarantee a minimum viable budget.
     * Ensures every team can cover mandatory infrastructure + a minimum transfer budget.
     */
    private function calculateSubsidy(int $projectedSurplus, int $carriedDebt, int $carriedSurplus, int $loanRepayment = 0, int $tier = 1): int
    {
        $minimumTransferBudget = self::MINIMUM_TRANSFER_BUDGET_BY_TIER[$tier] ?? self::MINIMUM_TRANSFER_BUDGET_DEFAULT;
        $minimumInfrastructure = GameInvestment::minimumInfrastructureForCompetitionTier($tier);
        $minimumAvailable = $minimumInfrastructure + $minimumTransferBudget;
        $rawAvailable = $projectedSurplus + $carriedSurplus - $carriedDebt - $loanRepayment;

        $gapSubsidy = max(0, $minimumAvailable - $rawAvailable);
        $baselineSubsidy = self::SUBSIDY_BASELINE_BY_TIER[$tier] ?? 0;

        return max($gapSubsidy, $baselineSubsidy);
    }
}
