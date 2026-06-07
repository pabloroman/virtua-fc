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
        private readonly GameStadiumNameResolver $nameResolver,
        private readonly FanLoyaltyService $fanLoyaltyService,
        private readonly NotificationService $notificationService,
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
            $this->createRenewalOffer($game, $active, $tier);
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
            'annual_value_cents' => $this->tierMidpointValue($tier),
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
            $deal = $this->createOffer($game, $tier);
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
        if (! $this->windowOpen($game)) {
            return false;
        }

        if (GameStadiumNamingDeal::activeForGame($game->id, $game->team_id)) {
            return false;
        }

        $cap = (int) config('commercial.naming_rights.max_pending_offers', 3);
        if ($this->pendingOfferCount($game) >= $cap) {
            return false;
        }

        return $this->seekCooldownRemainingDays($game) === 0;
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
     * Mint an offer regardless of any cooldown/fee, still respecting the
     * window, active-deal, board-full and sponsor-dedupe gates. For the debug
     * command and tests, where deterministic arrival is needed.
     */
    public function forceOffer(Game $game): ?GameStadiumNamingDeal
    {
        if (! $this->canReceiveOffer($game)) {
            return null;
        }

        $tier = TeamReputation::resolveLevel($game->id, $game->team_id);

        return $this->createOffer($game, $tier);
    }

    /**
     * Whether the board has room for another offer: the identity window is
     * open, no deal is already running, and the pending count is under the
     * cap. (Cooldown/fee are seek-time concerns, not board-capacity ones.)
     */
    private function canReceiveOffer(Game $game): bool
    {
        if (! $this->windowOpen($game)) {
            return false;
        }

        if (GameStadiumNamingDeal::activeForGame($game->id, $game->team_id)) {
            return false;
        }

        $cap = (int) config('commercial.naming_rights.max_pending_offers', 3);

        return $this->pendingOfferCount($game) < $cap;
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

    /**
     * Create one pending offer: a sponsor not already on the table, a random
     * headline value within the club's reputation tier, and a random term.
     * Returns null when every brand is already pending.
     */
    private function createOffer(Game $game, string $tier): ?GameStadiumNamingDeal
    {
        $season = (int) $game->season;

        $sponsor = $this->pickAvailableSponsor($game, $season, $tier);
        if ($sponsor === null) {
            return null;
        }

        $valueRange = config("commercial.naming_rights.annual_value.{$tier}")
            ?? config('commercial.naming_rights.annual_value.local', [50_000_00, 200_000_00]);
        [$minValue, $maxValue] = $valueRange;

        $minSeasons = (int) config('commercial.naming_rights.min_contract_seasons', 1);
        $maxSeasons = (int) config('commercial.naming_rights.max_contract_seasons', 5);

        return GameStadiumNamingDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'sponsor_name' => $sponsor['name'],
            'proposed_stadium_name' => $sponsor['stadium'],
            'annual_value_cents' => random_int((int) $minValue, (int) $maxValue),
            'contract_seasons' => random_int($minSeasons, $maxSeasons),
            'is_renewal' => false,
            'status' => GameStadiumNamingDeal::STATUS_PENDING,
            'offered_season' => $season,
        ]);
    }

    /**
     * Mint a free renewal offer for the incumbent sponsor when a deal expires:
     * same sponsor and stadium name, a fresh fee re-priced from the club's
     * current reputation tier, and a one-season term so the decision returns
     * each pre-season. Flagged is_renewal so acceptance skips the loyalty shock
     * (the name does not change). No agency fee or cooldown — keeping a sponsor
     * you already have costs nothing; shopping for a different one still goes
     * through the paid seek flow.
     */
    private function createRenewalOffer(Game $game, GameStadiumNamingDeal $expired, string $tier): GameStadiumNamingDeal
    {
        $season = (int) $game->season;

        $valueRange = config("commercial.naming_rights.annual_value.{$tier}")
            ?? config('commercial.naming_rights.annual_value.local', [50_000_00, 200_000_00]);
        [$minValue, $maxValue] = $valueRange;

        $seasons = (int) config('commercial.naming_rights.renewal_seasons', 1);

        return GameStadiumNamingDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'sponsor_name' => $expired->sponsor_name,
            'proposed_stadium_name' => $expired->proposed_stadium_name,
            'annual_value_cents' => random_int((int) $minValue, (int) $maxValue),
            'contract_seasons' => $seasons,
            'is_renewal' => true,
            'status' => GameStadiumNamingDeal::STATUS_PENDING,
            'offered_season' => $season,
        ]);
    }

    /**
     * Deterministic fee for a tier: the midpoint of the tier's annual-value
     * band. Used to price pre-existing deals a club starts the game with — no
     * per-club value is authored, so the fee simply tracks club stature.
     */
    private function tierMidpointValue(string $tier): int
    {
        $range = config("commercial.naming_rights.annual_value.{$tier}")
            ?? config('commercial.naming_rights.annual_value.local', [50_000_00, 200_000_00]);
        [$min, $max] = $range;

        return intdiv((int) $min + (int) $max, 2);
    }

    /**
     * Pick a sponsor brand eligible for this club that isn't already pending
     * this pre-season, so competing offers never duplicate the same name. Two
     * gates narrow the pool:
     *   1. `reach` must bid for the club's tier (a regional brewer won't chase
     *      a superclub; a global giant won't bother with a third-tier ground).
     *      An unmapped tier skips this gate so the board never silently starves.
     *   2. a non-global brand only operates in its home market, so it can only
     *      name a ground in its own country; global brands name grounds anywhere
     *      (Emirates, Coca-Cola) and carry no `country`.
     * Null when no eligible brand is left.
     *
     * @return array{name: string, reach: string, country?: string, stadium: string}|null
     */
    private function pickAvailableSponsor(Game $game, int $season, string $tier): ?array
    {
        $sponsors = config('commercial.naming_rights.sponsors', []);
        if (empty($sponsors)) {
            return null;
        }

        $eligibleReaches = (array) config("commercial.naming_rights.sponsor_reach_by_tier.{$tier}", []);
        $country = $game->country;
        $sponsors = array_filter($sponsors, function (array $sponsor) use ($eligibleReaches, $country) {
            $reach = $sponsor['reach'] ?? null;

            if (! empty($eligibleReaches) && ! in_array($reach, $eligibleReaches, true)) {
                return false;
            }

            // Global brands are country-agnostic; everyone else is home-market only.
            return $reach === 'global' || ($sponsor['country'] ?? null) === $country;
        });

        $taken = GameStadiumNamingDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
            ->where('offered_season', $season)
            ->pluck('sponsor_name')
            ->all();

        $available = array_values(array_filter(
            $sponsors,
            fn (array $sponsor) => ! in_array($sponsor['name'], $taken, true),
        ));

        if (empty($available)) {
            return null;
        }

        return $available[random_int(0, count($available) - 1)];
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
        $deal = GameStadiumNamingDeal::activeForGame($game->id, $game->team_id);
        if (! $deal) {
            return 0;
        }

        return (int) $deal->annual_value_cents;
    }

    // ── Read sides ────────────────────────────────────────────────────────

    /**
     * Identity read-side for the stadium page: the current name, where it
     * comes from, and whether a cosmetic rename is allowed. The sponsorship
     * money surface lives on the Commercial page (buildCommercialPanel).
     *
     * @return array{stadiumIdentity: array<string, mixed>}
     */
    public function buildIdentityPanel(Game $game): array
    {
        $season = (int) $game->season;
        $windowOpen = $this->windowOpen($game);
        $active = GameStadiumNamingDeal::activeForGame($game->id, $game->team_id);
        $stadium = $this->stadiumRow($game);

        return [
            'stadiumIdentity' => [
                'currentName' => $this->effectiveName($game),
                'source' => $this->nameSource($active, $stadium),
                'windowOpen' => $windowOpen,
                'canRename' => $windowOpen
                    && $active === null
                    && ! ($stadium && $stadium->name_changed_season === $season),
                'hasActiveDeal' => $active !== null,
                'sponsorName' => $active?->sponsor_name,
            ],
        ];
    }

    /**
     * Commercial read-side for the Commercial page: the active naming-rights
     * deal (if any), the pending offer board, and the proactive-search state
     * (whether the manager can seek, the agency fee, and any cooldown).
     *
     * @return array{namingRights: array<string, mixed>}
     */
    public function buildCommercialPanel(Game $game): array
    {
        $season = (int) $game->season;
        $windowOpen = $this->windowOpen($game);
        $active = GameStadiumNamingDeal::activeForGame($game->id, $game->team_id);
        $stadium = $this->stadiumRow($game);

        $activeDeal = null;
        if ($active !== null) {
            $activeDeal = [
                'sponsor_name' => $active->sponsor_name,
                'annual_value_cents' => $active->annual_value_cents,
                'end_season' => $active->end_season,
                'seasons_remaining' => max(0, ($active->end_season ?? $season) - $season + 1),
            ];
        }

        $offers = [];
        if ($active === null) {
            $offers = GameStadiumNamingDeal::query()
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
                ->where('offered_season', $season)
                ->orderByDesc('annual_value_cents')
                ->get()
                ->map(fn (GameStadiumNamingDeal $deal) => [
                    'id' => $deal->id,
                    'sponsor_name' => $deal->sponsor_name,
                    'proposed_stadium_name' => $deal->proposed_stadium_name,
                    'annual_value_cents' => $deal->annual_value_cents,
                    'contract_seasons' => $deal->contract_seasons,
                    'is_renewal' => $deal->is_renewal,
                ])
                ->all();
        }

        return [
            'namingRights' => [
                'currentName' => $this->effectiveName($game),
                'source' => $this->nameSource($active, $stadium),
                'windowOpen' => $windowOpen,
                'activeDeal' => $activeDeal,
                'offers' => $offers,
                'seek' => [
                    'canSeek' => $this->canSeek($game),
                    'feeCents' => $this->searchFee($game),
                    'availableCashCents' => $this->availableCash($game),
                    'cooldownDays' => $this->seekCooldownRemainingDays($game),
                    'cooldownLength' => (int) config('commercial.naming_rights.search_cooldown_days', 14),
                ],
            ],
        ];
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
     * BudgetProjectionService already depends on this service.
     */
    private function availableCash(Game $game): int
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
        $stadium = $this->stadiumRow($game) ?? new GameStadium([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'base_capacity' => (int) ($game->team?->stadium_seats ?? 0),
        ]);

        $stadium->naming_rights_last_sought_date = $game->current_date->toDateString();
        $stadium->save();
    }

    private function setStadiumName(Game $game, string $name, ?int $renameSeason = null): void
    {
        $stadium = $this->stadiumRow($game) ?? new GameStadium([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'base_capacity' => (int) ($game->team?->stadium_seats ?? 0),
        ]);

        $stadium->stadium_name = $name;
        if ($renameSeason !== null) {
            $stadium->name_changed_season = $renameSeason;
        }
        $stadium->save();

        $this->nameResolver->clearCache();
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
            $this->nameResolver->clearCache();
        }
    }

    private function stadiumRow(Game $game): ?GameStadium
    {
        return GameStadium::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();
    }

    private function effectiveName(Game $game): ?string
    {
        return $this->nameResolver->effectiveName(
            $game->id,
            $game->team_id,
            $game->team?->stadium_name,
        );
    }

    /**
     * Where the current name comes from: an active sponsorship, a custom
     * rename, or the historic ground name.
     */
    private function nameSource(?GameStadiumNamingDeal $active, ?GameStadium $stadium): string
    {
        return $active !== null
            ? 'sponsor'
            : (($stadium && $stadium->stadium_name !== null) ? 'custom' : 'historic');
    }
}
