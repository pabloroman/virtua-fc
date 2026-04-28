<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\TeamReputation;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages AI-generated transfer market listings.
 *
 * AI teams list players for sale on the public market during transfer windows.
 * The user can browse these listings and bid on players via the existing
 * negotiation flow.
 *
 * Listing prices on this surface are showcase numbers — the negotiation flow
 * recomputes the full importance/leverage price the moment the user bids.
 */
class TransferMarketService
{
    /** Maximum number of AI listings active at any time */
    private const MAX_LISTINGS = 50;

    /**
     * Soft-fill threshold: refresh only tops up new listings when the
     * current count drops below this, giving the market stable browsing
     * between natural churn events instead of replacing listings daily.
     */
    private const SOFT_FILL_THRESHOLD = 30;

    /** Listings expire after this many days */
    private const LISTING_EXPIRY_DAYS = 30;

    /** Minimum squad depth per position group — never list below this */
    private const MIN_GROUP_COUNTS = [
        'Goalkeeper' => 3,
        'Defender' => 6,
        'Midfielder' => 6,
        'Forward' => 4,
    ];

    /** Minimum squad size below which a team will not list */
    private const MIN_SQUAD_SIZE = 20;

    /**
     * One listing per sampled team. Forces variety (every listing comes
     * from a different club) and means the team-sample size equals the
     * slot count — no overshoot or per-team cap math needed.
     */
    private const MAX_PICKS_PER_TEAM = 1;

    /** Listing-price multiplier per step of tier-vs-reputation gap. */
    private const LISTING_PRICE_TIER_GAP_STEP = 0.07;

    /** ±jitter applied to the listing-price multiplier so identical profiles diverge. */
    private const LISTING_PRICE_JITTER = 0.075;

    /** Hard floor and ceiling on the listing-price multiplier. */
    private const LISTING_PRICE_MIN_MULTIPLIER = 0.8;

    private const LISTING_PRICE_MAX_MULTIPLIER = 1.25;

    /**
     * Refresh AI market listings. Called every matchday, year-round.
     *
     * Always removes expired listings. Only tops up new listings when the
     * active count drops below SOFT_FILL_THRESHOLD, so the market is stable
     * between natural churn events instead of replacing rows daily.
     */
    public function refreshListings(Game $game): void
    {
        // Remove expired listings
        TransferListing::where('game_id', $game->id)
            ->where('team_id', '!=', $game->team_id)
            ->where('status', TransferListing::STATUS_LISTED)
            ->whereNotNull('asking_price')
            ->where('listed_at', '<', $game->current_date->copy()->subDays(self::LISTING_EXPIRY_DAYS))
            ->delete();

        // Count current AI listings
        $currentCount = TransferListing::where('game_id', $game->id)
            ->whereNotIn('team_id', $game->userTeamIds())
            ->where('status', TransferListing::STATUS_LISTED)
            ->whereNotNull('asking_price')
            ->count();

        // Soft-fill: only top up when the market has noticeably decayed
        if ($currentCount >= self::SOFT_FILL_THRESHOLD) {
            return;
        }

        $slotsAvailable = self::MAX_LISTINGS - $currentCount;

        $teamRepIndices = $this->sampleAITeams($game, $slotsAvailable);

        if (empty($teamRepIndices)) {
            return;
        }

        $teamRosters = $this->loadRostersFor($game, array_keys($teamRepIndices));

        if ($teamRosters->isEmpty()) {
            return;
        }

        $groupCounts = $teamRosters->map(function ($players) {
            return $players->groupBy(fn (GamePlayer $p) => $p->position_group)
                ->map->count();
        });

        // Players already listed or transferred this season
        $alreadyListedIds = TransferListing::where('game_id', $game->id)
            ->pluck('game_player_id')
            ->flip()
            ->all();

        $alreadyTransferredIds = GameTransfer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->pluck('game_player_id')
            ->flip()
            ->all();

        $excludedIds = $alreadyListedIds + $alreadyTransferredIds;

        $candidates = $this->buildListingCandidates(
            $teamRosters,
            $teamRepIndices,
            $groupCounts,
            $excludedIds,
            $game->current_date,
        );

        // Shuffle for cross-team variety, then take available slots
        $selected = $candidates->shuffle()->take($slotsAvailable);

        $rows = [];
        foreach ($selected as $candidate) {
            $player = $candidate['player'];
            $repIndex = $teamRepIndices[$player->team_id] ?? 0;

            $rows[] = [
                'id' => (string) Str::uuid(),
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'team_id' => $player->team_id,
                'status' => TransferListing::STATUS_LISTED,
                'listed_at' => $game->current_date->toDateString(),
                'asking_price' => $this->listingAskingPrice($player, $repIndex),
            ];
        }

        if (! empty($rows)) {
            TransferListing::insert($rows);
        }
    }

    /**
     * Get active market listings for the view.
     *
     * Excludes players for whom the user already has an agreed offer
     * waiting on a window — otherwise the user would see "available" rows
     * for players they've already bought.
     */
    public function getMarketListings(Game $game): Collection
    {
        $alreadyAgreedIds = TransferOffer::where('game_id', $game->id)
            ->where('offering_team_id', $game->team_id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->pluck('game_player_id');

        return TransferListing::with(['gamePlayer.player', 'gamePlayer.team'])
            ->where('game_id', $game->id)
            ->whereNotIn('team_id', $game->userTeamIds())
            ->where('status', TransferListing::STATUS_LISTED)
            ->whereNotNull('asking_price')
            ->whereNotIn('game_player_id', $alreadyAgreedIds)
            ->orderByDesc('asking_price')
            ->get();
    }

    // ── Private helpers ─────────────────────────────────────────────────

    /**
     * Score each eligible player from each sampled team and return up to
     * MAX_PICKS_PER_TEAM candidates per team.
     *
     * @param  array<string, int>  $teamRepIndices  team_id => reputation index (0-4)
     * @param  array<string, true>  $excludedIds  set of game_player_ids to skip
     */
    private function buildListingCandidates(
        Collection $teamRosters,
        array $teamRepIndices,
        Collection $groupCounts,
        array $excludedIds,
        Carbon $currentDate,
    ): Collection {
        $candidates = collect();

        foreach ($teamRosters as $teamId => $players) {
            if ($players->count() <= self::MIN_SQUAD_SIZE) {
                continue;
            }

            $repIndex = $teamRepIndices[$teamId] ?? 0;
            $teamGroupCounts = $groupCounts->get($teamId, collect());

            $teamPicks = $players
                ->filter(fn (GamePlayer $p) => ! $p->retiring_at_season && ! isset($excludedIds[$p->id]))
                ->map(fn (GamePlayer $p) => $this->scoreListable(
                    $p,
                    $repIndex,
                    $teamGroupCounts,
                    $currentDate,
                ))
                ->filter()
                ->sortByDesc('score')
                ->take(self::MAX_PICKS_PER_TEAM)
                ->values();

            $candidates = $candidates->concat($teamPicks);
        }

        return $candidates;
    }

    /**
     * Score a player as a listing candidate. Returns null if unlistable.
     *
     * Saleability uses the player's tier vs the team's reputation index:
     * "selling upward" (player tier above team's reputation) and "clearing
     * fringe" (player tier below team's reputation) both count as listing
     * signals. Players whose tier matches or exceeds the club's reputation
     * are protected unless their contract is running out — clubs only let
     * starter-level players go when free-departure leverage is gone.
     *
     * @return array{player: GamePlayer, score: int}|null
     */
    private function scoreListable(
        GamePlayer $player,
        int $teamRepIndex,
        Collection $teamGroupCounts,
        Carbon $currentDate,
    ): ?array {
        $group = $player->position_group;
        $groupFloor = self::MIN_GROUP_COUNTS[$group] ?? 4;
        $groupCount = $teamGroupCounts->get($group, 0);

        if ($groupCount <= $groupFloor) {
            return null;
        }

        $yearsLeft = $player->contract_until
            ? (int) $currentDate->diffInYears($player->contract_until)
            : 0;

        // Tier gap: positive ⇒ player above team level, negative ⇒ below.
        $tierGap = $this->tierGap($player, $teamRepIndex);

        if ($tierGap >= 0 && $yearsLeft > 1) {
            return null;
        }

        $score = 0;

        // Surplus at position
        $surplus = $groupCount - $groupFloor;
        if ($surplus > 0) {
            $score += min(5, $surplus * 2);
        }

        // Tier-vs-reputation signal — both directions count.
        if ($tierGap >= 2) {
            $score += 6;       // selling upward — strong
        } elseif ($tierGap >= 1) {
            $score += 3;       // mild upward
        } elseif ($tierGap <= -2) {
            $score += 3;       // clearing fringe
        } elseif ($tierGap === -1) {
            $score += 1;       // weak clearing signal
        }

        // Past-prime age is a classic clearing signal
        if ($player->age($currentDate) >= PlayerAge::PRIME_END) {
            $score += 3;
        }

        // Contract leverage: short contracts push clubs to list; long
        // contracts slightly discourage listing.
        if ($player->contract_until) {
            if ($yearsLeft <= 1) {
                $score += 6;
            } elseif ($yearsLeft <= 2) {
                $score += 3;
            } elseif ($yearsLeft >= 4) {
                $score -= 2;
            }
        }

        // Jitter so identical profiles don't always order the same way
        $score += mt_rand(0, 2);

        if ($score < 3) {
            return null;
        }

        return ['player' => $player, 'score' => $score];
    }

    /**
     * Showcase listing price = market_value × tier-gap-driven multiplier.
     *
     * Cheap on purpose — the negotiation flow recomputes the full
     * importance/leverage price the moment the user bids.
     */
    private function listingAskingPrice(GamePlayer $player, int $teamRepIndex): int
    {
        $tierGap = $this->tierGap($player, $teamRepIndex);
        $jitter = mt_rand(
            -(int) (self::LISTING_PRICE_JITTER * 1000),
            (int) (self::LISTING_PRICE_JITTER * 1000),
        ) / 1000.0;

        $multiplier = 1.0 + ($tierGap * self::LISTING_PRICE_TIER_GAP_STEP) + $jitter;
        $multiplier = max(self::LISTING_PRICE_MIN_MULTIPLIER, min(self::LISTING_PRICE_MAX_MULTIPLIER, $multiplier));

        return Money::roundPrice((int) round($player->market_value_cents * $multiplier));
    }

    private function tierGap(GamePlayer $player, int $teamRepIndex): int
    {
        $playerTierIndex = ($player->tier ?? 1) - 1;

        return $playerTierIndex - $teamRepIndex;
    }

    /**
     * Pick K random AI teams from this game's competition entries — that
     * source spans every country in the game (team_reputations is seeded
     * domestic-only, so sampling from it would silently scope listings
     * to the user's country). Reputation resolves via TeamReputation,
     * which falls back to ClubProfile for foreign clubs that have no
     * game-scoped rep row.
     *
     * @return array<string, int>  team_id => reputation index (0-4)
     */
    private function sampleAITeams(Game $game, int $sampleSize): array
    {
        $teamIds = DB::table('competition_entries')
            ->where('game_id', $game->id)
            ->whereNotIn('team_id', $game->userTeamIds())
            ->distinct()
            ->pluck('team_id')
            ->all();

        if (empty($teamIds)) {
            return [];
        }

        shuffle($teamIds);
        $sampled = array_slice($teamIds, 0, $sampleSize);

        return TeamReputation::resolveLevels($game->id, $sampled)
            ->mapWithKeys(fn (string $level, string $teamId) => [
                $teamId => ClubProfile::getReputationTierIndex($level),
            ])
            ->all();
    }

    /**
     * Load rosters for a fixed set of team_ids, grouped by team_id.
     *
     * @param  array<string>  $teamIds
     */
    private function loadRostersFor(Game $game, array $teamIds): Collection
    {
        if (empty($teamIds)) {
            return collect();
        }

        return GamePlayer::with(['player:id,date_of_birth'])
            ->select([
                'id', 'game_id', 'player_id', 'team_id', 'position',
                'market_value_cents', 'tier',
                'retiring_at_season', 'contract_until', 'annual_wage',
            ])
            ->where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->groupBy('team_id');
    }
}
