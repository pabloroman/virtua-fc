<?php

namespace App\Modules\Stadium\Services;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GameStadium;
use App\Models\GameStadiumNamingDeal;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Modules\Notification\Services\NotificationService;
use InvalidArgumentException;

/**
 * Orchestrates stadium naming: cosmetic renames and naming-rights
 * sponsorship deals.
 *
 * Both levers are gated to the pre-season identity window (pre-season
 * through the first league matchday). A naming-rights deal pays a fixed
 * recurring annual fee, in exchange for a one-time fan-loyalty shock at
 * signing. A cosmetic rename has no fan effect and is limited to once per
 * season.
 *
 * Sponsors do NOT arrive unsolicited. The manager seeks them proactively
 * from the Commercial page (seekSponsors()), which charges a commercial-
 * agency fee and starts a cooldown — the friction that keeps recurring
 * sponsor income from becoming free money on tap.
 *
 * Income math lives here (projection + settlement) so the Finance and
 * Season modules call into Stadium rather than the other way around,
 * preserving the module dependency direction.
 */
class NamingRightsService
{
    public function __construct(
        private readonly GameStadiumResolver $stadiumResolver,
        private readonly FanLoyaltyService $fanLoyaltyService,
        private readonly NotificationService $notificationService,
        private readonly NamingOfferFactory $offerFactory,
    ) {}

    // ── Window ──────────────────────────────────────────────────────────

    /**
     * Stadium identity can only change in the pre-season window: from the
     * start of pre-season through the first league matchday. Once the
     * league is under way (next unplayed league round > 1) the window shuts
     * until next pre-season. Mirrors how season-ticket pricing locks.
     */
    public function windowOpen(Game $game): bool
    {
        if ($game->pre_season) {
            return true;
        }

        return $game->nextLeagueMatchday === 1;
    }

    // ── Season rollover (pre-season processor) ───────────────────────────

    /**
     * Roll naming-rights deals over into a new season: expire any deal that
     * has run its term (handing the stadium name back to whatever it was
     * before the sponsorship — a custom rename, or the historic name), offer
     * the incumbent sponsor a free renewal, and clear unaccepted offers left
     * over from previous pre-seasons.
     *
     * Only the incumbent renewal is minted here; fresh sponsor offers are
     * sought by the manager from the Commercial page (seekSponsors()).
     * Idempotent — once a deal is expired it is no longer the active deal, so
     * a re-run mints no second renewal.
     */
    public function rolloverForNewSeason(Game $game): void
    {
        $season = (int) $game->season;

        // Expire a deal that ended last season, restore the pre-deal name, and
        // give the incumbent sponsor a free renewal offer for the new pre-season.
        $active = GameStadiumNamingDeal::activeForGame($game->id, $game->team_id);
        if ($active && $active->end_season !== null && $active->end_season < $season) {
            $active->update(['status' => GameStadiumNamingDeal::STATUS_EXPIRED]);
            $this->restorePreDealName($game, $active);

            $tier = TeamReputation::resolveLevel($game->id, $game->team_id);
            $this->offerFactory->createRenewalOffer($game, $active, $tier);
        }

        // Clear unaccepted offers from previous pre-seasons.
        GameStadiumNamingDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
            ->where('offered_season', '<', $season)
            ->update(['status' => GameStadiumNamingDeal::STATUS_EXPIRED]);
    }

    /**
     * One-off pre-season nudge that the commercial window is open and the
     * manager can seek sponsors. Replaces the old per-offer arrival
     * notification: discoverability now comes from a single prompt that
     * deep-links to the Commercial page, not a stream of unsolicited offers.
     * No-op when a deal is already running (there is nothing to seek).
     */
    public function notifyCommercialWindowOpen(Game $game): void
    {
        if (GameStadiumNamingDeal::activeForGame($game->id, $game->team_id)) {
            return;
        }

        $this->notificationService->create(
            game: $game,
            type: GameNotification::TYPE_COMMERCIAL,
            title: __('notifications.commercial_window_open_title'),
            message: __('notifications.commercial_window_open_message'),
            priority: GameNotification::PRIORITY_INFO,
        );
    }

    /**
     * Materialise the pre-existing real-world naming deal a club starts the
     * game already wearing (e.g. "Spotify Camp Nou"), if it has one. Runs once
     * at game setup: creates an ACTIVE deal from the config overlay
     * (config/commercial.php `preexisting_naming_deals`, keyed by
     * transfermarkt_id), brands the per-game stadium row, and prices the fee
     * from the club's reputation tier.
     *
     * Idempotent: a no-op once any naming-deal row exists for the club, so it
     * never re-creates after the seeded deal later expires or the user trades
     * offers. No fan-loyalty shock (the fans already live with the name) and no
     * finance refresh — the budget-projection processor that runs afterwards
     * picks the deal up. Returns null when the club has no pre-existing deal.
     */
    public function seedInitialDeal(Game $game): ?GameStadiumNamingDeal
    {
        if ($game->isTournamentMode()) {
            return null;
        }

        $transfermarktId = $game->team?->transfermarkt_id;
        if ($transfermarktId === null) {
            return null;
        }

        $overlay = (array) config('commercial.preexisting_naming_deals', []);
        // Config keys may be read as int or string depending on source; accept both.
        $entry = $overlay[$transfermarktId] ?? $overlay[(string) $transfermarktId] ?? null;
        if ($entry === null) {
            return null;
        }

        // Only at first setup, before any deal exists for the club.
        $alreadyHasDeal = GameStadiumNamingDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->exists();
        if ($alreadyHasDeal) {
            return null;
        }

        // The sponsored display name lives in the seed data (Team.stadium_name);
        // the overlay only supplies the brand and the clean revert name.
        $sponsoredName = $game->team?->stadium_name;
        if ($sponsoredName === null || $sponsoredName === '') {
            return null;
        }

        $season = (int) $game->season;
        $tier = TeamReputation::resolveLevel($game->id, $game->team_id);
        $seasons = (int) config('commercial.naming_rights.preexisting_deal_seasons', 1);

        $deal = GameStadiumNamingDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'sponsor_name' => $entry['sponsor'],
            'proposed_stadium_name' => $sponsoredName,
            'previous_stadium_name' => $entry['clean_name'] ?? null,
            'annual_value_cents' => $this->offerFactory->tierMidpointValue($tier),
            'contract_seasons' => $seasons,
            'is_renewal' => false,
            'status' => GameStadiumNamingDeal::STATUS_ACTIVE,
            'offered_season' => $season,
            'start_season' => $season,
            'end_season' => $season + $seasons - 1,
        ]);

        // Brand the per-game ground so the resolver shows the sponsor name and
        // the expiry revert guard (restorePreDealName) can later fire.
        $this->setStadiumName($game, $sponsoredName);

        return $deal;
    }

    // ── Proactive search (Commercial page) ───────────────────────────────

    /**
     * Seek naming-rights sponsors on demand: charge the commercial-agency
     * search fee, then top the offer board up to the pending cap with fresh
     * reputation-weighted offers. Gated to the pre-season window, no active
     * deal, a board that isn't already full, an elapsed cooldown since the
     * last search, and enough cash for the fee. Throws on any gate failure
     * (message keys mirror acceptOffer()).
     *
     * The fee + cooldown are the deliberate friction: a club can re-seek to
     * re-roll the board, but only after the cooldown and only by paying the
     * agency again — so chasing the top headline value has a real cost.
     *
     * @return array<int, GameStadiumNamingDeal> the offers minted this search
     */
    public function seekSponsors(Game $game): array
    {
        if (! $this->windowOpen($game)) {
            throw new InvalidArgumentException('messages.naming_rights_window_closed');
        }

        if (GameStadiumNamingDeal::activeForGame($game->id, $game->team_id)) {
            throw new InvalidArgumentException('messages.naming_rights_deal_active');
        }

        $cap = (int) config('commercial.naming_rights.max_pending_offers', 3);
        $pending = $this->pendingOfferCount($game);
        if ($pending >= $cap) {
            throw new InvalidArgumentException('messages.naming_rights_board_full');
        }

        if ($this->seekCooldownRemainingDays($game) > 0) {
            throw new InvalidArgumentException('messages.naming_rights_search_cooldown');
        }

        $tier = TeamReputation::resolveLevel($game->id, $game->team_id);
        $fee = $this->searchFee($game, $tier);
        if ($fee > $this->availableCash($game)) {
            throw new InvalidArgumentException('messages.naming_rights_search_unaffordable');
        }

        if ($fee > 0) {
            $this->chargeSearchFee($game, $fee);
        }

        // Top the board up to the cap with fresh offers (one per remaining
        // slot; the loop stops early only if the sponsor pool is exhausted).
        $minted = [];
        for ($slot = $pending; $slot < $cap; $slot++) {
            $deal = $this->offerFactory->createOffer($game, $tier);
            if ($deal === null) {
                break;
            }
            $minted[] = $deal;
        }

        $this->stampLastSought($game);

        return $minted;
    }

    /**
     * Whether the manager can seek sponsors right now: the window is open, no
     * deal is running, the board isn't full, and the cooldown has elapsed.
     * Fee affordability is reported separately (searchFee + availableCash) so
     * the UI can distinguish "can't seek yet" from "can't afford the fee".
     */
    public function canSeek(Game $game): bool
    {
        return $this->seekGateOpen(
            $this->windowOpen($game),
            GameStadiumNamingDeal::activeForGame($game->id, $game->team_id),
            $this->pendingOfferCount($game),
            $this->seekCooldownRemainingDays($game),
        );
    }

    /**
     * The seek gate, evaluated from already-fetched state so callers that
     * have these values in hand (buildCommercialPanel) don't re-query them.
     */
    private function seekGateOpen(bool $windowOpen, ?GameStadiumNamingDeal $active, int $pendingOffers, int $cooldownDays): bool
    {
        $cap = (int) config('commercial.naming_rights.max_pending_offers', 3);

        return $windowOpen
            && $active === null
            && $pendingOffers < $cap
            && $cooldownDays === 0;
    }

    /**
     * The commercial-agency fee for one search, in cents, by club stature.
     */
    public function searchFee(Game $game, ?string $tier = null): int
    {
        $tier ??= TeamReputation::resolveLevel($game->id, $game->team_id);
        $fees = (array) config('commercial.naming_rights.search_fee', []);

        return (int) ($fees[$tier] ?? ($fees['local'] ?? 0));
    }

    /**
     * Whole game-calendar days left before the club may seek again. Zero when
     * the club has never searched this game or the cooldown has elapsed.
     */
    public function seekCooldownRemainingDays(Game $game): int
    {
        $last = $this->stadiumRow($game)?->naming_rights_last_sought_date;
        if ($last === null) {
            return 0;
        }

        $cooldown = (int) config('commercial.naming_rights.search_cooldown_days', 14);
        $nextAllowed = $last->copy()->addDays($cooldown);

        // Date-cast values sit at midnight, so a timestamp delta gives whole
        // days without Carbon-version sign/float ambiguity.
        $secondsLeft = $nextAllowed->getTimestamp() - $game->current_date->getTimestamp();

        return $secondsLeft <= 0 ? 0 : (int) ceil($secondsLeft / 86_400);
    }

    /**
     * Pending naming-rights offers on the board for the current season.
     */
    private function pendingOfferCount(Game $game): int
    {
        return GameStadiumNamingDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
            ->where('offered_season', (int) $game->season)
            ->count();
    }

    // ── Player actions ───────────────────────────────────────────────────

    /**
     * Accept a pending naming-rights offer: activate it, reject the rest,
     * brand the stadium, inflict the one-time loyalty shock, and fold the
     * projected income into the current season's finances.
     */
    public function acceptOffer(Game $game, string $dealId): GameStadiumNamingDeal
    {
        if (! $this->windowOpen($game)) {
            throw new InvalidArgumentException('messages.naming_rights_window_closed');
        }

        if (GameStadiumNamingDeal::activeForGame($game->id, $game->team_id)) {
            throw new InvalidArgumentException('messages.naming_rights_deal_active');
        }

        $deal = GameStadiumNamingDeal::query()
            ->where('id', $dealId)
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
            ->first();

        if (! $deal) {
            throw new InvalidArgumentException('messages.naming_rights_offer_unavailable');
        }

        $season = (int) $game->season;

        // Capture the name in effect now (a custom rename, or null = historic)
        // so the ground can revert to it when the sponsorship expires instead
        // of always dropping back to the historic name.
        $previousName = GameStadium::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->value('stadium_name');

        $deal->update([
            'status' => GameStadiumNamingDeal::STATUS_ACTIVE,
            'start_season' => $season,
            'end_season' => $season + $deal->contract_seasons - 1,
            'previous_stadium_name' => $previousName,
        ]);

        // Reject the competing offers the player passed over.
        GameStadiumNamingDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
            ->where('id', '!=', $deal->id)
            ->update(['status' => GameStadiumNamingDeal::STATUS_REJECTED]);

        // Brand the ground (the sponsor takes the pen — manual rename locks).
        $this->setStadiumName($game, $deal->proposed_stadium_name);

        // A renewal keeps the existing name, so there is no fresh betrayal of
        // the fans — only a new sponsor (a name change) costs loyalty.
        if (! $deal->is_renewal) {
            $this->applyLoyaltyShock($game);
        }
        $this->refreshProjectedNamingRights($game);

        return $deal;
    }

    /**
     * Cosmetic rename. No fan effect; just the pre-season window and a
     * once-per-season cooldown. Blocked while a naming-rights deal owns the
     * name.
     */
    public function rename(Game $game, string $name): void
    {
        if (! $this->windowOpen($game)) {
            throw new InvalidArgumentException('messages.naming_rights_window_closed');
        }

        if (GameStadiumNamingDeal::activeForGame($game->id, $game->team_id)) {
            throw new InvalidArgumentException('messages.naming_rights_deal_active');
        }

        $season = (int) $game->season;
        $stadium = $this->stadiumRow($game);

        if ($stadium && $stadium->name_changed_season === $season) {
            throw new InvalidArgumentException('messages.stadium_already_renamed');
        }

        $this->setStadiumName($game, $name, $season);
    }

    // ── Revenue (called by Finance projection / Season settlement) ────────

    /**
     * Projected naming-rights income for the season: the active deal's fixed
     * annual fee. Zero when no deal is active.
     */
    public function projectedRevenueForGame(Game $game): int
    {
        $deal = GameStadiumNamingDeal::activeForGame($game->id, $game->team_id);
        if (! $deal) {
            return 0;
        }

        return (int) $deal->annual_value_cents;
    }

    /**
     * Settled naming-rights income: the active deal's fixed annual fee — the
     * sponsor pays the same regardless of attendance. Zero when no deal is
     * active. Mirrors projectedRevenueForGame so projection and settlement
     * agree exactly.
     */
    public function settledRevenueForGame(Game $game): int
    {
        // The sponsor pays the same fixed annual fee regardless of attendance,
        // so settlement equals the projection exactly. Delegating keeps the two
        // in lockstep instead of relying on the formulas being kept identical.
        return $this->projectedRevenueForGame($game);
    }

    // ── Internals ─────────────────────────────────────────────────────────

    private function applyLoyaltyShock(Game $game): void
    {
        $rep = TeamReputation::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        if (! $rep) {
            return;
        }

        $factor = (float) config('commercial.naming_rights.loyalty_shock_factor', 0.12);
        $delta = -(int) round($rep->base_loyalty * $factor);

        $this->fanLoyaltyService->applyDelta($rep, $delta);
    }

    /**
     * Recompute the projected naming-rights line on the current finances row
     * and fold the difference into projected totals/surplus. Keeps the
     * budget consistent whether a deal is signed before or after the
     * pre-season projection ran. No-op when there's no finances row yet.
     */
    private function refreshProjectedNamingRights(Game $game): void
    {
        $finances = $game->currentFinances;
        if (! $finances) {
            return;
        }

        $new = $this->projectedRevenueForGame($game);
        $delta = $new - (int) $finances->projected_naming_rights_revenue;
        if ($delta === 0) {
            return;
        }

        $finances->projected_naming_rights_revenue = $new;
        $finances->projected_total_revenue += $delta;
        $finances->projected_surplus += $delta;
        $finances->save();
    }

    /**
     * Available cash for one-off commercial spend, in cents: the unspent
     * transfer budget net of committed bids. Mirrors how stadium cash
     * purchases gauge affordability, so the agency fee competes with the
     * transfer kitty rather than being free. Replicated (not delegated to
     * StadiumUpgradeService) to avoid a service-resolution cycle, since
     * BudgetProjectionService already depends on this service. Public so the
     * commercial read panel (NamingRightsReadService) can show affordability.
     */
    public function availableCash(Game $game): int
    {
        $investment = $game->currentInvestment;
        if (! $investment) {
            return 0;
        }

        return max(0, (int) $investment->transfer_budget - TransferOffer::committedBudget($game->id));
    }

    /**
     * Charge the agency fee: decrement the live transfer budget and log the
     * expense, exactly as a cash-financed stadium purchase does.
     */
    private function chargeSearchFee(Game $game, int $fee): void
    {
        $game->currentInvestment?->decrement('transfer_budget', $fee);

        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_AGENT_FEE,
            amount: $fee,
            description: __('finances.tx_naming_rights_search_fee'),
            transactionDate: $game->current_date->toDateString(),
        );
    }

    private function stampLastSought(Game $game): void
    {
        $stadium = $this->getOrCreateStadiumRow($game);
        $stadium->naming_rights_last_sought_date = $game->current_date->toDateString();
        $stadium->save();
    }

    private function setStadiumName(Game $game, string $name, ?int $renameSeason = null): void
    {
        $stadium = $this->getOrCreateStadiumRow($game);
        $stadium->stadium_name = $name;
        if ($renameSeason !== null) {
            $stadium->name_changed_season = $renameSeason;
        }
        $stadium->save();

        $this->stadiumResolver->clearCache();
    }

    /**
     * Hand the stadium name back when a sponsorship expires: restore whatever
     * it was before the deal (a custom rename, or null = historic). Only acts
     * when the sponsor still owns the name — a manager who renamed the ground
     * again after the deal ended keeps their newer choice.
     */
    private function restorePreDealName(Game $game, GameStadiumNamingDeal $deal): void
    {
        $stadium = $this->stadiumRow($game);

        if ($stadium && $stadium->stadium_name === $deal->proposed_stadium_name) {
            $stadium->stadium_name = $deal->previous_stadium_name;
            $stadium->save();
            $this->stadiumResolver->clearCache();
        }
    }

    private function stadiumRow(Game $game): ?GameStadium
    {
        return GameStadium::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();
    }

    /**
     * The stadium row for this game, or a fresh (unsaved) one seeded with the
     * team's defaults so callers can set a field and save without branching.
     */
    private function getOrCreateStadiumRow(Game $game): GameStadium
    {
        return $this->stadiumRow($game) ?? new GameStadium([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'base_capacity' => (int) ($game->team?->stadium_seats ?? 0),
        ]);
    }

}
