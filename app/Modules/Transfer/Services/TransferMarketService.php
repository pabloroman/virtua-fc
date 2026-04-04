<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferListing;
use App\Modules\Player\PlayerAge;
use App\Support\Money;
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

    /** Listings expire after this many days */
    private const LISTING_EXPIRY_DAYS = 30;

    /** Ideal squad depth per position group — never list below this */
    private const MIN_GROUP_COUNTS = [
        'Goalkeeper' => 3,
        'Defender' => 6,
        'Midfielder' => 6,
        'Forward' => 4,
    ];

    private const IDEAL_GROUP_COUNTS = [
        'Goalkeeper' => 3,
        'Defender' => 6,
        'Midfielder' => 6,
        'Forward' => 4,
    ];

    /** Minimum squad size below which a team will not list */
    private const MIN_SQUAD_SIZE = 20;

    /** Percentage chance each listing is a clearing type (vs upgrade) */
    private const CLEARING_CHANCE = 65;

    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    /**
     * Refresh AI market listings. Called each matchday during transfer windows.
     *
     * Removes expired listings, then generates new ones up to MAX_LISTINGS.
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

        $slotsAvailable = self::MAX_LISTINGS - $currentCount;
        if ($slotsAvailable <= 0) {
            return;
        }

        // Load context
        $teamRosters = $this->loadAIRosters($game);
        $teamAverages = $teamRosters->map(fn ($players) => $this->calculateTeamAverage($players));
        $teamReputations = TeamReputation::resolveLevels($game->id, $teamRosters->keys()->toArray());

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

        $groupCounts = $teamRosters->map(function ($players) {
            return $players->groupBy(fn ($p) => $this->getPositionGroup($p->position))
                ->map->count();
        });

        // Build candidate sell offers using the same scoring as AITransferMarketService
        $candidates = $this->buildListingCandidates(
            $teamRosters,
            $teamAverages,
            $groupCounts,
            $excludedIds,
            $game->current_date,
        );

        // Take up to available slots, shuffle for variety
        $selected = $candidates->shuffle()->take($slotsAvailable);

        // Create listings
        foreach ($selected as $candidate) {
            $player = $candidate['player'];
            $askingPrice = $this->scoutingService->calculateAskingPrice($player);

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
     * Clear all AI listings when the transfer window closes.
     */
    public function clearListings(Game $game): void
    {
        TransferListing::where('game_id', $game->id)
            ->where('team_id', '!=', $game->team_id)
            ->where('status', TransferListing::STATUS_LISTED)
            ->whereNotNull('asking_price')
            ->delete();
    }

    /**
     * Get market listings for the view, optionally filtered by position group.
     */
    public function getMarketListings(Game $game, ?string $positionFilter = null): Collection
    {
        $query = TransferListing::with(['gamePlayer.player', 'gamePlayer.team'])
            ->where('game_id', $game->id)
            ->where('team_id', '!=', $game->team_id)
            ->where('status', TransferListing::STATUS_LISTED)
            ->whereNotNull('asking_price');

        if ($positionFilter && $positionFilter !== 'all') {
            $query->whereHas('gamePlayer', function ($q) use ($positionFilter) {
                $positions = match ($positionFilter) {
                    'gk' => ['Goalkeeper'],
                    'def' => ['Centre-Back', 'Left-Back', 'Right-Back'],
                    'mid' => ['Defensive Midfield', 'Central Midfield', 'Attacking Midfield', 'Left Midfield', 'Right Midfield'],
                    'fwd' => ['Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker'],
                    default => [],
                };
                if (!empty($positions)) {
                    $q->whereIn('position', $positions);
                }
            });
        }

        return $query->orderByDesc('asking_price')->get();
    }

    // ── Private helpers ─────────────────────────────────────────────────

    /**
     * Build candidate listings from AI team rosters.
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

            $eligible = $players->filter(
                fn (GamePlayer $p) => !$p->retiring_at_season && !isset($excludedIds[$p->id])
            );

            // Score candidates
            $clearingCandidates = $eligible
                ->map(fn ($p) => $this->scoreClearingCandidate($p, $teamAvg, $teamGroupCounts, $currentDate))
                ->filter();

            $upgradeCandidates = $eligible
                ->map(fn ($p) => $this->scoreUpgradeCandidate($p, $teamAvg, $teamGroupCounts, $currentDate))
                ->filter();

            // Pick 1-3 per team
            $teamPicks = mt_rand(1, 3);
            $usedIds = [];

            for ($i = 0; $i < $teamPicks; $i++) {
                $isClearing = mt_rand(1, 100) <= self::CLEARING_CHANCE;
                $pool = $isClearing ? $clearingCandidates : $upgradeCandidates;
                $candidate = $pool->sortByDesc('score')
                    ->first(fn ($c) => !isset($usedIds[$c['player']->id]));

                // Fallback to other pool
                if (!$candidate) {
                    $pool = $isClearing ? $upgradeCandidates : $clearingCandidates;
                    $candidate = $pool->sortByDesc('score')
                        ->first(fn ($c) => !isset($usedIds[$c['player']->id]));
                }

                if (!$candidate) {
                    break;
                }

                $usedIds[$candidate['player']->id] = true;
                $candidates->push($candidate);
            }
        }

        return $candidates;
    }

    private function scoreClearingCandidate(GamePlayer $player, int $teamAvg, Collection $teamGroupCounts, Carbon $currentDate): ?array
    {
        $ability = $this->getPlayerAbility($player);
        $group = $this->getPositionGroup($player->position);
        $groupCount = $teamGroupCounts->get($group, 0);

        if ($groupCount <= (self::MIN_GROUP_COUNTS[$group] ?? 2)) {
            return null;
        }

        $score = 0;

        $surplus = $groupCount - (self::IDEAL_GROUP_COUNTS[$group] ?? 4);
        if ($surplus > 0) {
            $score += $surplus * 3;
        }

        $abilityGap = $teamAvg - $ability;
        if ($abilityGap > 15) {
            $score += 5;
        } elseif ($abilityGap > 5) {
            $score += 3;
        } elseif ($abilityGap > 0) {
            $score += 1;
        }

        $age = $player->age($currentDate);
        if ($age >= PlayerAge::PRIME_END) {
            $score += 3;
        }

        $score += mt_rand(0, 2);

        if ($score < 3) {
            return null;
        }

        return ['player' => $player, 'score' => $score];
    }

    private function scoreUpgradeCandidate(GamePlayer $player, int $teamAvg, Collection $teamGroupCounts, Carbon $currentDate): ?array
    {
        $ability = $this->getPlayerAbility($player);
        $group = $this->getPositionGroup($player->position);
        $groupCount = $teamGroupCounts->get($group, 0);

        if ($groupCount <= (self::MIN_GROUP_COUNTS[$group] ?? 2)) {
            return null;
        }

        if ($ability < $teamAvg) {
            return null;
        }

        $score = 0;

        $abilityGap = $ability - $teamAvg;
        $score += min(5, (int) ($abilityGap / 3));

        $age = $player->age($currentDate);
        if ($age >= PlayerAge::YOUNG_END && $age <= PlayerAge::primePhaseAge(0.5)) {
            $score += 3;
        } elseif ($age >= PlayerAge::ACADEMY_END && $age < PlayerAge::YOUNG_END) {
            $score += 1;
        }

        $surplus = $groupCount - (self::IDEAL_GROUP_COUNTS[$group] ?? 4);
        if ($surplus > 0) {
            $score += min(4, $surplus * 2);
        }

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

    private function getPositionGroup(string $position): string
    {
        return match ($position) {
            'Goalkeeper' => 'Goalkeeper',
            'Centre-Back', 'Left-Back', 'Right-Back' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker' => 'Forward',
            default => 'Midfielder',
        };
    }
}
