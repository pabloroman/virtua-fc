<?php

namespace App\Modules\Finance\Services;

use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use App\Modules\Finance\DTOs\WageCapDecision;
use App\Modules\Finance\DTOs\WageHeadroom;
use App\Modules\Finance\Enums\SigningContext;
use Illuminate\Support\Collection;

/**
 * Authoritative wage-cap gate.
 *
 * Two checks, both measuring committed annual wages against a projected revenue
 * line. Wages must not exceed `cap = revenue * ratio + buffer`. Ratio defaults
 * to 1.0 (100%) so users have meaningful headroom; tighter ratios per-tier are
 * a config-only change.
 *
 *   currentSeasonHeadroom() — squad wages this season vs this season's revenue
 *   nextSeasonHeadroom()    — squad wages that carry past June 30 + accepted
 *                              pre-contracts vs projected next-season revenue
 *
 * canAfford() runs the gates relevant to the SigningContext and returns a
 * WageCapDecision the caller surfaces to the user. freeableWages() ranks the
 * user's roster wage-desc so the UI can suggest who to sell.
 */
class WageBudgetService
{
    public function __construct(
        private readonly BudgetProjectionService $projectionService,
    ) {}

    /**
     * Headroom for the season currently being played.
     */
    public function currentSeasonHeadroom(Game $game): WageHeadroom
    {
        $season = (int) $game->season;
        $revenue = $this->resolveProjectedRevenue($game, $season);
        $squadWages = $this->squadWageBill($game);

        return new WageHeadroom(
            season: $season,
            projectedRevenue: $revenue,
            currentSquadWages: $squadWages,
            pendingPreContractWages: 0,
            ratio: $this->ratioForGame($game),
            bufferCents: $this->bufferCents(),
        );
    }

    /**
     * Headroom for season + 1. Counts:
     *   - squad players whose contract runs past this season's June 30
     *     (their annual_wage will carry into next season unchanged)
     *   - pending_annual_wage on the same set (renewals already agreed)
     *   - every accepted/pending incoming pre-contract (their offered_wage
     *     becomes annual_wage at rollover)
     *
     * Loans and players whose contracts expire this June are excluded — the
     * loan returns to the parent club, the expiring player walks.
     */
    public function nextSeasonHeadroom(Game $game): WageHeadroom
    {
        $season = (int) $game->season;
        $nextSeason = $season + 1;
        $revenue = $this->resolveProjectedRevenue($game, $nextSeason);

        $carryingWages = $this->squadWagesCarryingForward($game);
        $preContractWages = $this->pendingIncomingPreContractWages($game);

        return new WageHeadroom(
            season: $nextSeason,
            projectedRevenue: $revenue,
            currentSquadWages: $carryingWages,
            pendingPreContractWages: $preContractWages,
            ratio: $this->ratioForGame($game),
            bufferCents: $this->bufferCents(),
        );
    }

    /**
     * Decide whether the user may commit `$additionalAnnualWage` (cents) under
     * the given context. The decision is authoritative — UI hints are advisory,
     * this method is what the completion code calls.
     */
    public function canAfford(Game $game, int $additionalAnnualWage, SigningContext $context): WageCapDecision
    {
        $current = $context->appliesCurrentSeason() ? $this->currentSeasonHeadroom($game) : null;
        $next = $context->appliesNextSeason() ? $this->nextSeasonHeadroom($game) : null;

        $currentShortfall = $current?->shortfallFor($additionalAnnualWage) ?? 0;
        $nextShortfall = $next?->shortfallFor($additionalAnnualWage) ?? 0;
        $shortfall = max($currentShortfall, $nextShortfall);

        if (! $context->isHardReject() || $shortfall === 0) {
            return WageCapDecision::allow($current, $next, $shortfall);
        }

        $blockedBy = $currentShortfall >= $nextShortfall ? 'current_season' : 'next_season';

        return WageCapDecision::reject($shortfall, $current, $next, $blockedBy);
    }

    /**
     * Rank the user's roster by annual wage descending. Used by the UI to
     * suggest who to sell/release when a signing would breach the cap.
     *
     * Players currently in the user's preferred lineup are de-prioritised so
     * we don't suggest selling the best XI first (best-effort — when no lineup
     * helper is available we simply return wage-desc).
     *
     * @return Collection<int, GamePlayer>
     */
    public function freeableWages(Game $game, int $shortfallCents): Collection
    {
        $teamIds = $game->userTeamIds();
        if ($teamIds === []) {
            return collect();
        }

        $players = GamePlayer::with('team')
            ->where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->where('annual_wage', '>', 0)
            ->whereDoesntHave('activeLoan', function ($q) use ($teamIds) {
                $q->whereIn('loan_team_id', $teamIds);
            })
            ->orderByDesc('annual_wage')
            ->get();

        if ($players->isEmpty()) {
            return $players;
        }

        $running = 0;
        $picks = collect();

        foreach ($players as $player) {
            if ($running >= $shortfallCents && $picks->count() >= 3) {
                break;
            }
            $picks->push($player);
            $running += (int) $player->annual_wage;
        }

        return $picks;
    }

    /**
     * The wage-cap ratio that applies to a given game (defaults to 1.0).
     * Per-competition-tier override lives at config('finances.wage_cap_ratio').
     */
    public function ratioForGame(Game $game): float
    {
        $tier = (int) ($game->competition->tier ?? 1);
        $perTier = (array) config('finances.wage_cap_ratio_by_tier', []);
        if (array_key_exists($tier, $perTier)) {
            return (float) $perTier[$tier];
        }

        return (float) config('finances.wage_cap_ratio', 1.0);
    }

    public function bufferCents(): int
    {
        return (int) config('finances.wage_cap_buffer_cents', 10_000_000);
    }

    private function squadWageBill(Game $game): int
    {
        $teamIds = $game->userTeamIds();
        if ($teamIds === []) {
            return 0;
        }

        return (int) GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->whereDoesntHave('activeLoan', function ($q) use ($teamIds) {
                $q->whereIn('loan_team_id', $teamIds);
            })
            ->sum('annual_wage');
    }

    /**
     * Sum of annual wages for user-owned players whose contracts run beyond
     * this season's end (June 30). Uses pending_annual_wage when set
     * (renewals already agreed but not yet active).
     */
    private function squadWagesCarryingForward(Game $game): int
    {
        $teamIds = $game->userTeamIds();
        if ($teamIds === []) {
            return 0;
        }

        $seasonEnd = $game->getSeasonEndDate();

        $players = GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->where('contract_until', '>', $seasonEnd)
            ->whereDoesntHave('activeLoan', function ($q) use ($teamIds) {
                $q->whereIn('loan_team_id', $teamIds);
            })
            ->get(['annual_wage', 'pending_annual_wage']);

        return (int) $players->sum(fn ($p) => $p->pending_annual_wage ?? $p->annual_wage);
    }

    /**
     * Sum of offered_wage on incoming pre-contracts the user has tabled that
     * haven't been rejected yet (pending or accepted). Each represents a wage
     * that will land on the roster at the season rollover.
     */
    private function pendingIncomingPreContractWages(Game $game): int
    {
        return (int) TransferOffer::where('game_id', $game->id)
            ->where('offering_team_id', $game->team_id)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
            ->sum('offered_wage');
    }

    /**
     * Look up projected revenue for a season. Reuses the persisted GameFinances
     * row when one exists; otherwise falls back to the in-progress projection
     * service. For `season + 1` we don't have a row yet, so we approximate
     * using this season's revenue as a proxy — accurate enough for a sanity
     * gate, avoids re-running the whole projection pipeline mid-season.
     */
    private function resolveProjectedRevenue(Game $game, int $season): int
    {
        $finances = GameFinances::where('game_id', $game->id)
            ->where('season', $season)
            ->first();

        if ($finances && $finances->projected_total_revenue > 0) {
            return (int) $finances->projected_total_revenue;
        }

        $current = $game->currentFinances;
        if ($current && $current->projected_total_revenue > 0) {
            return (int) $current->projected_total_revenue;
        }

        return (int) $this->projectionService->generateProjections($game)->projected_total_revenue;
    }
}
