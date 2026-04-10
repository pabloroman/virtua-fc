<?php

namespace App\Modules\Transfer\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\TransferListing;
use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Manages AI-generated transfer market listings.
 *
 * AI teams list players for sale on the public market during transfer windows.
 * The user can browse these listings and bid on players via the existing
 * negotiation flow.
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

    /** Max listings contributed by any single AI team per refresh */
    private const MAX_PICKS_PER_TEAM = 3;

    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

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
            ->where('team_id', '!=', $game->team_id)
            ->where('status', TransferListing::STATUS_LISTED)
            ->whereNotNull('asking_price')
            ->count();

        // Soft-fill: only top up when the market has noticeably decayed
        if ($currentCount >= self::SOFT_FILL_THRESHOLD) {
            return;
        }

        $slotsAvailable = self::MAX_LISTINGS - $currentCount;

        // Load context
        $teamRosters = $this->loadAIRosters($game);
        $teamAverages = $teamRosters->map(fn ($players) => $this->calculateTeamAverage($players));
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
            $teamAverages,
            $groupCounts,
            $excludedIds,
            $game->current_date,
        );

        // Shuffle for cross-team variety, then take available slots
        $selected = $candidates->shuffle()->take($slotsAvailable);

        foreach ($selected as $candidate) {
            $player = $candidate['player'];
            $askingPrice = $this->scoutingService->calculateAskingPrice($player, $game->current_date);

            TransferListing::create([
                'game_id' => $game->id,
                'game_player_id' => $player->id,
                'team_id' => $player->team_id,
                'status' => TransferListing::STATUS_LISTED,
                'listed_at' => $game->current_date,
                'asking_price' => $askingPrice,
            ]);
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
            ->where('team_id', '!=', $game->team_id)
            ->where('status', TransferListing::STATUS_LISTED)
            ->whereNotNull('asking_price')
            ->whereNotIn('game_player_id', $alreadyAgreedIds)
            ->orderByDesc('asking_price')
            ->get();
    }

    // ── Private helpers ─────────────────────────────────────────────────

    /**
     * Score each eligible player from each team and return up to
     * MAX_PICKS_PER_TEAM candidates per team.
     */
    private function buildListingCandidates(
        Collection $teamRosters,
        Collection $teamAverages,
        Collection $groupCounts,
        array $excludedIds,
        Carbon $currentDate,
    ): Collection {
        $candidates = collect();

        foreach ($teamRosters as $teamId => $players) {
            if ($players->count() <= self::MIN_SQUAD_SIZE) {
                continue;
            }

            $teamAvg = $teamAverages[$teamId] ?? 55;
            $teamGroupCounts = $groupCounts->get($teamId, collect());

            // Top 11 players by ability ≈ core XI. Used as a hard filter so
            // clubs don't put their starters on the open market.
            $coreIds = $players
                ->sortByDesc(fn (GamePlayer $p) => $this->getPlayerAbility($p))
                ->take(11)
                ->pluck('id')
                ->flip()
                ->all();

            $teamPicks = $players
                ->filter(fn (GamePlayer $p) => !$p->retiring_at_season && !isset($excludedIds[$p->id]))
                ->map(fn (GamePlayer $p) => $this->scoreListable(
                    $p,
                    $teamAvg,
                    $teamGroupCounts,
                    $currentDate,
                    isset($coreIds[$p->id]),
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
     * One scoring pool: both "clearing surplus depth" and "selling a player
     * above team average" earn score here. Core players (top-11 by ability)
     * are protected unless their contract is running out.
     *
     * @return array{player: GamePlayer, score: int}|null
     */
    private function scoreListable(
        GamePlayer $player,
        int $teamAvg,
        Collection $teamGroupCounts,
        Carbon $currentDate,
        bool $isCorePlayer,
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

        // Protect starters unless their contract is running down — a club
        // will only sell a core player when renewal has failed and free
        // departure is the alternative.
        if ($isCorePlayer && $yearsLeft > 1) {
            return null;
        }

        $ability = $this->getPlayerAbility($player);
        $score = 0;

        // Surplus at position
        $surplus = $groupCount - $groupFloor;
        if ($surplus > 0) {
            $score += min(5, $surplus * 2);
        }

        // Ability gap vs team average — both directions are a listing signal.
        // Below average ⇒ clearing weak depth. Above average ⇒ selling upward.
        $gap = abs($ability - $teamAvg);
        if ($gap > 15) {
            $score += 5;
        } elseif ($gap > 5) {
            $score += 3;
        } elseif ($gap > 0) {
            $score += 1;
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

    private function loadAIRosters(Game $game): Collection
    {
        return GamePlayer::with(['player:id,date_of_birth'])
            ->select([
                'id', 'game_id', 'player_id', 'team_id', 'position',
                'market_value_cents', 'game_technical_ability', 'game_physical_ability',
                'retiring_at_season', 'contract_until', 'annual_wage',
            ])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->get()
            ->groupBy('team_id');
    }

    private function calculateTeamAverage(Collection $players): int
    {
        if ($players->isEmpty()) {
            return 55;
        }

        $total = $players->sum(fn (GamePlayer $p) => $this->getPlayerAbility($p));

        return (int) round($total / $players->count());
    }

    private function getPlayerAbility(GamePlayer $player): int
    {
        $tech = $player->game_technical_ability ?? 50;
        $phys = $player->game_physical_ability ?? 50;

        return (int) round(($tech + $phys) / 2);
    }
}
