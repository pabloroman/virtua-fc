<?php

namespace App\Modules\Transfer\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Support\Money;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Modules\Player\PlayerAge;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;

class ScoutingService
{

    /**
     * Scouting tier effects on searches.
     * [weeks_reduction, extra_results, ability_fuzz_reduction]
     */
    private const SCOUTING_TIER_EFFECTS = [
        0 => [0, 0, 0],   // No scouting department
        1 => [0, 0, 0],   // Basic - domestic only, baseline
        2 => [0, 1, 2],   // Good - domestic only, 1 extra result, 2 less fuzz
        3 => [1, 2, 4],   // Excellent - international, 1 week faster, 2 extra results, 4 less fuzz
        4 => [1, 3, 6],   // World-class - international, 1 week faster, 3 extra results, 6 less fuzz
    ];

    /** Minimum scouting tier required for international searches. */
    private const INTERNATIONAL_SEARCH_MIN_TIER = 3;

    /**
     * Tracking tier configuration.
     * [max_concurrent_slots, matchdays_to_level_1, matchdays_to_level_2]
     */
    private const TRACKING_TIER_CONFIG = [
        0 => [0, 0, 0],
        1 => [1, 1, 3],
        2 => [2, 1, 2],
        3 => [3, 1, 2],
        4 => [4, 1, 1],
    ];

    /** Maximum players on the shortlist at any time. */
    public const MAX_SHORTLIST_SIZE = 20;

    /** Maximum scout search reports kept at any time. */
    public const MAX_SEARCH_HISTORY = 20;

    public function __construct(
        private readonly ContractService $contractService,
        private readonly DispositionService $dispositionService,
    ) {}

    /**
     * Check if a game's scouting tier allows international searches.
     */
    public function canSearchInternationally(Game $game): bool
    {
        $tier = $game->currentInvestment->scouting_tier ?? 1;

        return $tier >= self::INTERNATIONAL_SEARCH_MIN_TIER;
    }

    // =========================================
    // SCOUT SEARCH
    // =========================================


    /**
     * Get the currently searching scout report for a game.
     */
    public function getActiveReport(Game $game): ?ScoutReport
    {
        return ScoutReport::where('game_id', $game->id)
            ->where('status', ScoutReport::STATUS_SEARCHING)
            ->first();
    }

    /**
     * Get all completed search reports for a game, ordered by most recent.
     */
    public function getSearchHistory(Game $game): Collection
    {
        return ScoutReport::where('game_id', $game->id)
            ->where('status', ScoutReport::STATUS_COMPLETED)
            ->orderByDesc('game_date')
            ->get();
    }

    /**
     * Start a new scout search.
     */
    public function startSearch(Game $game, array $filters): ScoutReport
    {
        // Enforce domestic-only scope for low scouting tiers
        if (!$this->canSearchInternationally($game)) {
            $filters['scope'] = ['domestic'];
        }

        $weeks = $this->calculateSearchWeeks($filters, $game);

        return ScoutReport::create([
            'game_id' => $game->id,
            'status' => ScoutReport::STATUS_SEARCHING,
            'filters' => $filters,
            'weeks_total' => $weeks,
            'weeks_remaining' => $weeks,
            'game_date' => $game->current_date,
        ]);
    }

    /**
     * Cancel an active scout search.
     */
    public function cancelSearch(ScoutReport $report): void
    {
        $report->update(['status' => ScoutReport::STATUS_CANCELLED]);
    }

    /**
     * Calculate how many weeks a search takes.
     * Higher scouting tier = faster searches.
     */
    private function calculateSearchWeeks(array $filters, ?Game $game = null): int
    {
        $position = $filters['position'] ?? '';
        $scope = $filters['scope'] ?? ['domestic', 'international'];

        // Base weeks calculation
        $baseWeeks = 2; // Medium search

        // Broad search (position group like "any defender")
        if (str_starts_with($position, 'any_')) {
            $baseWeeks = 3;
        }
        // Narrow search (specific position + domestic only)
        elseif (count($scope) === 1 && in_array('domestic', $scope)) {
            $baseWeeks = 1;
        }

        // Apply scouting tier reduction
        if ($game) {
            $tier = $game->currentInvestment->scouting_tier ?? 1;
            $reduction = self::SCOUTING_TIER_EFFECTS[$tier][0] ?? 0;
            $baseWeeks = max(1, $baseWeeks - $reduction);
        }

        return $baseWeeks;
    }

    /**
     * Tick scout search progress. Called on matchday advance.
     * If search completes, generates results.
     */
    public function tickSearch(Game $game): ?ScoutReport
    {
        $report = ScoutReport::where('game_id', $game->id)
            ->where('status', ScoutReport::STATUS_SEARCHING)
            ->first();

        if (! $report) {
            return null;
        }

        $completed = $report->tickWeek();

        if ($completed) {
            $this->generateResults($game, $report);
        }

        return $report;
    }

    /**
     * Generate scout results for a completed search.
     */
    private function generateResults(Game $game, ScoutReport $report): void
    {
        $filters = $report->filters;
        $positions = PositionMapper::getPositionsForFilter($filters['position']) ?? [];

        // Enforce international search tier restriction
        if (!$this->canSearchInternationally($game)) {
            $filters['scope'] = ['domestic'];
        }

        $queryBuilder = app(ScoutSearchQueryBuilder::class);
        $candidates = $queryBuilder->buildCandidateQuery($game, $filters, $positions)->get();

        if ($candidates->isEmpty()) {
            $report->update([
                'status' => ScoutReport::STATUS_COMPLETED,
                'player_ids' => [],
            ]);

            return;
        }

        // Pre-load all team rosters for candidates to avoid N+1 queries
        $candidateTeamIds = $candidates->pluck('team_id')->unique();
        $teamRosters = GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $candidateTeamIds)
            ->get()
            ->groupBy('team_id');

        // Score each player by availability (lower importance = more available)
        $scored = $candidates->map(function ($player) use ($teamRosters) {
            $teammates = $teamRosters->get($player->team_id, collect());
            $importance = $this->calculatePlayerImportance($player, $teammates);

            return [
                'player' => $player,
                'importance' => $importance,
                'availability_score' => 1.0 - $importance + (mt_rand(0, 100) / 200), // Add randomness
            ];
        });

        // Sort by availability (highest = most available)
        $sorted = $scored->sortByDesc('availability_score');

        // Base result count: 5-8 players
        $baseCount = rand(5, 8);

        // Apply scouting tier bonus for extra results
        $tier = $game->currentInvestment->scouting_tier ?? 1;
        $extraResults = self::SCOUTING_TIER_EFFECTS[$tier][1] ?? 0;

        // Take players, biased toward available ones but include 1-2 stretch targets
        $count = min($candidates->count(), $baseCount + $extraResults);

        // Get the most available ones
        $available = $sorted->take(max($count - 2, 3));

        // Add 1-2 stretch targets (high importance but good stats)
        $stretchTargets = $sorted->filter(fn ($s) => $s['importance'] > 0.6)
            ->sortByDesc(fn ($s) => $s['player']->overall_score)
            ->take(min(2, $count - $available->count()));

        $selected = $available->merge($stretchTargets)->unique(fn ($s) => $s['player']->id)->take($count);

        $playerIds = $selected->pluck('player.id')->values()->toArray();

        $report->update([
            'status' => ScoutReport::STATUS_COMPLETED,
            'player_ids' => $playerIds,
        ]);
    }

    // =========================================
    // ASKING PRICE CALCULATION
    // =========================================

    /**
     * Calculate the AI's asking price for a player.
     */
    public function calculateAskingPrice(GamePlayer $player, Carbon $currentDate): int
    {
        $base = $player->market_value_cents;
        $importance = $this->calculatePlayerImportance($player);

        // Contract leverage: a club can only charge an importance premium if
        // it has leverage to refuse bids. As the contract runs down that
        // leverage decays — an expiring star is worth about what a buyer
        // would pay for any player they can pick up free next window.
        $leverage = $this->getContractLeverage($player, $currentDate);
        $effectiveImportance = $importance * $leverage;

        // Importance multiplier: 0.8x for worst (or no leverage), 1.0x for
        // average, 1.2x for the club's best player on a long contract.
        $importanceMultiplier = 0.8 + ($effectiveImportance * 0.4);

        // Contract modifier (fee discount from less time remaining)
        $contractModifier = $this->getContractModifier($player, $currentDate);

        // Age modifier
        $ageModifier = $this->getAgeModifier($player->age($currentDate));

        $totalMultiplier = $importanceMultiplier * $contractModifier * $ageModifier;

        // Important players are never sold below market value — the club's
        // reluctance (see DispositionService::clubSellDisposition) is driven
        // by raw importance, so the asking price floor must be too. Contract
        // leverage decay still reduces the premium above market value via
        // $importanceMultiplier, but shouldn't drag a key player's price
        // below 1.0x. Non-key players can be discounted down to 0.75x.
        $floor = $importance >= 0.5 ? 1.0 : 0.75;
        $totalMultiplier = min(max($totalMultiplier, $floor), 1.5);

        $askingPrice = $base * $totalMultiplier;

        return Money::roundPrice((int) $askingPrice);
    }

    /**
     * Calculate player importance within their team (0.0 to 1.0).
     *
     * @param GamePlayer $player
     * @param Collection|null $teammates Pre-loaded teammates to avoid repeated queries
     */
    public function calculatePlayerImportance(GamePlayer $player, ?Collection $teammates = null): float
    {
        return $this->dispositionService->playerImportance($player, $teammates);
    }

    /**
     * Get the contract leverage factor (0.0 to 1.0).
     *
     * A club can only charge an importance premium if it has leverage to
     * refuse bids. As the contract runs down that leverage decays — at the
     * expiring end there is no premium because the buyer can simply wait and
     * sign free.
     */
    private function getContractLeverage(GamePlayer $player, Carbon $currentDate): float
    {
        if (! $player->contract_until) {
            return 0.0;
        }

        $yearsLeft = $currentDate->diffInYears($player->contract_until);

        if ($yearsLeft >= 4) {
            return 1.0;
        }
        if ($yearsLeft >= 3) {
            return 0.85;
        }
        if ($yearsLeft >= 2) {
            return 0.65;
        }
        if ($yearsLeft >= 1) {
            return 0.30;
        }

        return 0.0; // Expiring
    }

    /**
     * Get contract years modifier for asking price.
     */
    private function getContractModifier(GamePlayer $player, Carbon $currentDate): float
    {
        if (! $player->contract_until) {
            return 0.5;
        }

        $yearsLeft = $currentDate->diffInYears($player->contract_until);

        if ($yearsLeft >= 4) {
            return 1.2;
        }
        if ($yearsLeft >= 3) {
            return 1.1;
        }
        if ($yearsLeft >= 2) {
            return 1.0;
        }
        if ($yearsLeft >= 1) {
            return 0.85;
        }

        return 0.5; // Expiring
    }

    /**
     * Get age modifier for asking price.
     */
    private function getAgeModifier(int $age): float
    {
        if ($age < PlayerAge::YOUNG_END) {
            return 1.15;
        }
        if ($age <= PlayerAge::PRIME_END) {
            return 1.0;
        }

        return max(0.5, 1.0 - ($age - PlayerAge::PRIME_END) * 0.05);
    }

    // =========================================
    // TRANSFER BID EVALUATION
    // =========================================

    /**
     * Evaluate a transfer bid from the user.
     *
     * @return array{result: string, counter_amount: int|null, message: string}
     */
    /**
     * @return array{result: string, counter_amount: int|null, asking_price: int, message: string}
     */
    public function evaluateBid(GamePlayer $player, int $bidAmount, ?Game $game = null, ?int $previousCounter = null): array
    {
        $currentDate = $game?->current_date ?? $player->game->current_date;
        $askingPrice = $this->calculateAskingPrice($player, $currentDate);

        // Use the previous counter as ceiling so the club never raises their demand
        $ceiling = ($previousCounter !== null && $previousCounter < $askingPrice)
            ? $previousCounter
            : $askingPrice;

        $ratio = $bidAmount / max($ceiling, 1);
        $isKeyPlayer = $this->isKeyPlayer($player);

        $acceptThreshold = $isKeyPlayer ? 1.05 : 0.95;
        $counterThreshold = $isKeyPlayer ? 0.85 : 0.75;

        if ($ratio >= $acceptThreshold) {
            return [
                'result' => 'accepted',
                'counter_amount' => null,
                'asking_price' => $askingPrice,
                'message' => __('transfers.bid_accepted', ['team' => $player->team?->name]),
            ];
        }

        if ($ratio >= $counterThreshold) {
            $counterAmount = (int) (($bidAmount + $ceiling) / 2);
            $counterAmount = Money::roundPrice($counterAmount);

            // If rounding makes counter equal to bid, just accept the bid
            if ($counterAmount <= $bidAmount) {
                return [
                    'result' => 'accepted',
                    'counter_amount' => null,
                    'asking_price' => $askingPrice,
                    'message' => __('transfers.bid_accepted', ['team' => $player->team?->name]),
                ];
            }

            return [
                'result' => 'counter',
                'counter_amount' => $counterAmount,
                'asking_price' => $askingPrice,
                'message' => __('transfers.counter_offer_made', ['team' => $player->team?->name, 'amount' => Money::format($counterAmount)]),
            ];
        }

        return [
            'result' => 'rejected',
            'counter_amount' => null,
            'asking_price' => $askingPrice,
            'message' => __('transfers.bid_rejected_too_low', ['team' => $player->team?->name]),
        ];
    }

    /**
     * Evaluate the user's counter-offer from the AI buyer's perspective.
     *
     * Called when the user counters an unsolicited or listed offer with a higher asking price.
     * The AI club evaluates whether to accept, counter, or walk away.
     *
     * @return array{result: string, counter_amount: int|null}
     */
    public function evaluateCounterOffer(TransferOffer $offer, int $userAskingPrice, Game $game): array
    {
        $player = $offer->gamePlayer;
        $marketValue = $player->market_value_cents;

        // Calculate AI club's squad value to determine budget ceiling
        $offeringTeamSquadValue = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $offer->offering_team_id)
            ->sum('market_value_cents');

        // AI club's max willingness: min of squad-value ceiling and market-value ceiling
        $squadValueCeiling = (int) ($offeringTeamSquadValue * 0.25);
        $marketValueCeiling = (int) ($marketValue * 1.30);
        $maxWillingness = min($squadValueCeiling, $marketValueCeiling);

        // Ensure max willingness is at least the current offer
        $maxWillingness = max($maxWillingness, $offer->transfer_fee);

        if ($userAskingPrice <= (int) ($maxWillingness * 0.95)) {
            return [
                'result' => 'accepted',
                'counter_amount' => null,
            ];
        }

        if ($userAskingPrice <= (int) ($maxWillingness * 1.15)) {
            // Counter with midpoint of user's ask and AI's current bid
            $counterAmount = (int) (($userAskingPrice + $offer->transfer_fee) / 2);
            $counterAmount = Money::roundPrice($counterAmount);

            // If rounding makes counter equal to or below the current bid, just accept
            if ($counterAmount <= $offer->transfer_fee) {
                return [
                    'result' => 'accepted',
                    'counter_amount' => null,
                ];
            }

            return [
                'result' => 'countered',
                'counter_amount' => $counterAmount,
            ];
        }

        return [
            'result' => 'rejected',
            'counter_amount' => null,
        ];
    }

    /**
     * Check if player is a key player (top 3 by ability on their team).
     */
    private function isKeyPlayer(GamePlayer $player): bool
    {
        $importance = $this->calculatePlayerImportance($player);

        return $importance > 0.85; // Roughly top 3 out of ~25 players
    }

    // =========================================
    // LOAN REQUEST EVALUATION
    // =========================================

    /**
     * Evaluate a loan request from the user.
     *
     * @return array{result: string, message: string}
     */
    public function evaluateLoanRequest(GamePlayer $player, ?Game $game = null): array
    {
        return $this->dispositionService->evaluateLoanRequest($player, $game);
    }

    // =========================================
    // SYNCHRONOUS LOAN EVALUATION
    // =========================================

    /**
     * Deterministic loan request evaluation for sync negotiation.
     * Returns result, asking loan fee, mood, and rejection reason.
     *
     * @return array{result: string, disposition: float, rejection_reason: ?string}
     */
    public function evaluateLoanRequestSync(GamePlayer $player, Game $game): array
    {
        return $this->dispositionService->evaluateLoanRequestSync($player, $game);
    }

    // =========================================
    // WAGE DEMAND
    // =========================================

    // =========================================
    // REPUTATION GATE
    // =========================================

    // =========================================
    // FREE AGENT REPUTATION GATE
    // =========================================

    /**
     * Check whether a free agent is willing to sign for a given team,
     * based on the player's tier vs the team's reputation.
     */
    public function canSignFreeAgent(GamePlayer $player, string $gameId, string $teamId): bool
    {
        return $this->dispositionService->canSignFreeAgent($player, $gameId, $teamId);
    }

    /**
     * Determine a free agent's willingness to sign for a team.
     *
     * @return string 'willing' (will sign), 'reluctant' (1 tier below minimum), or 'unwilling' (2+ below)
     */
    public function getFreeAgentWillingnessLevel(GamePlayer $player, string $gameId, string $teamId): string
    {
        return $this->dispositionService->freeAgentWillingnessLevel($player, $gameId, $teamId);
    }

    // =========================================
    // SCOUTING REPORT DATA
    // =========================================

    /**
     * Evaluate whether a player accepts a pre-contract offer based on offered wage vs demand,
     * reputation gap, and player ambition.
     *
     * @return array{accepted: bool, message: string}
     */
    public function evaluatePreContractOffer(GamePlayer $player, int $offeredWage, Team $biddingTeam): array
    {
        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::PRE_CONTRACT);

        return $this->dispositionService->evaluatePreContractOffer($player, $offeredWage, $demand['wage'], $biddingTeam);
    }

    /**
     * Get scouting detail for a specific player.
     */
    public function getPlayerScoutingDetail(GamePlayer $player, Game $game): array
    {
        $isFreeAgent = $player->team_id === null;
        $askingPrice = $isFreeAgent ? 0 : $this->calculateAskingPrice($player, $game->current_date);
        $transferDemand = $this->contractService->calculateWageDemand($player, NegotiationScenario::TRANSFER);
        $wageDemand = $transferDemand['wage'];
        $importance = $isFreeAgent ? 0.0 : $this->calculatePlayerImportance($player);

        // For expiring-contract players, show the premium wage demand
        $isExpiring = $player->contract_until && $player->contract_until <= $game->getSeasonEndDate();
        $preContractWageDemand = $isExpiring
            ? $this->contractService->calculateWageDemand($player, NegotiationScenario::PRE_CONTRACT)['wage']
            : null;

        $investment = $game->currentInvestment;
        $committedBudget = TransferOffer::committedBudget($game->id);
        $availableBudget = ($investment->transfer_budget ?? 0) - $committedBudget;
        $canAffordFee = $askingPrice <= $availableBudget;
        $canAffordLoan = $isFreeAgent || $wageDemand <= $availableBudget;

        // Fuzzy ability range - higher scouting tier = more accurate
        $techAbility = $player->current_technical_ability;
        $physAbility = $player->current_physical_ability;

        $tier = $game->currentInvestment->scouting_tier ?? 1;
        $fuzzReduction = self::SCOUTING_TIER_EFFECTS[$tier][2] ?? 0;
        $baseFuzz = rand(3, 7);
        $fuzz = max(1, $baseFuzz - $fuzzReduction);

        return [
            'player' => $player,
            'is_free_agent' => $isFreeAgent,
            'asking_price' => $askingPrice,
            'formatted_asking_price' => $isFreeAgent ? __('transfers.free_transfer') : Money::format($askingPrice),
            'wage_demand' => $wageDemand,
            'formatted_wage_demand' => Money::format($wageDemand),
            'pre_contract_wage_demand' => $preContractWageDemand,
            'importance' => $importance,
            'can_afford_fee' => $canAffordFee,
            'can_afford_loan' => $canAffordLoan,
            'available_budget' => $availableBudget,
            'transfer_budget' => $investment->transfer_budget ?? 0,
            'formatted_transfer_budget' => $investment ? $investment->formatted_transfer_budget : '€ 0',
            'tech_range' => [max(1, $techAbility - $fuzz), min(99, $techAbility + $fuzz)],
            'phys_range' => [max(1, $physAbility - $fuzz), min(99, $physAbility + $fuzz)],
        ];
    }

    // =========================================
    // PLAYER TRACKING
    // =========================================

    /**
     * Get tracking capacity info for a game.
     *
     * @return array{max_slots: int, used_slots: int, available_slots: int}
     */
    public function getTrackingCapacity(Game $game): array
    {
        $tier = $game->currentInvestment->scouting_tier ?? 0;
        $config = self::TRACKING_TIER_CONFIG[$tier] ?? self::TRACKING_TIER_CONFIG[0];
        $maxSlots = $config[0];

        $usedSlots = ShortlistedPlayer::where('game_id', $game->id)
            ->where('is_tracking', true)
            ->count();

        return [
            'max_slots' => $maxSlots,
            'used_slots' => $usedSlots,
            'available_slots' => max(0, $maxSlots - $usedSlots),
        ];
    }

    /**
     * Check if the shortlist is full.
     */
    public function isShortlistFull(Game $game): bool
    {
        return ShortlistedPlayer::where('game_id', $game->id)->count() >= self::MAX_SHORTLIST_SIZE;
    }

    /**
     * Start tracking a shortlisted player.
     */
    public function startTracking(ShortlistedPlayer $entry, Game $game): bool
    {
        if ($entry->is_tracking) {
            return false;
        }

        $capacity = $this->getTrackingCapacity($game);
        if ($capacity['available_slots'] <= 0) {
            return false;
        }

        $entry->update(['is_tracking' => true]);

        return true;
    }

    /**
     * Check if the search history is full.
     */
    public function isSearchHistoryFull(Game $game): bool
    {
        return ScoutReport::where('game_id', $game->id)
            ->whereIn('status', [ScoutReport::STATUS_SEARCHING, ScoutReport::STATUS_COMPLETED])
            ->count() >= self::MAX_SEARCH_HISTORY;
    }

    /**
     * Stop tracking a shortlisted player (retains gathered intel).
     */
    public function stopTracking(ShortlistedPlayer $entry): void
    {
        $entry->update(['is_tracking' => false]);
    }

    /**
     * Tick tracking progress for all tracked players. Called each matchday.
     * Returns entries that leveled up.
     */
    public function tickTracking(Game $game): Collection
    {
        $tier = $game->currentInvestment->scouting_tier ?? 0;
        $config = self::TRACKING_TIER_CONFIG[$tier] ?? self::TRACKING_TIER_CONFIG[0];
        $matchdaysToL1 = $config[1];
        $matchdaysToL2 = $config[1] + $config[2]; // cumulative

        $trackedEntries = ShortlistedPlayer::where('game_id', $game->id)
            ->where('is_tracking', true)
            ->where('intel_level', '<', ShortlistedPlayer::INTEL_DEEP)
            ->with('gamePlayer')
            ->get();

        $leveledUp = collect();

        foreach ($trackedEntries as $entry) {
            $newMatchdays = $entry->matchdays_tracked + 1;
            $oldLevel = $entry->intel_level;

            if ($newMatchdays >= $matchdaysToL2 && $oldLevel < ShortlistedPlayer::INTEL_DEEP) {
                $entry->update(['matchdays_tracked' => $newMatchdays, 'intel_level' => ShortlistedPlayer::INTEL_DEEP, 'is_tracking' => false]);
                $leveledUp->push($entry);
            } elseif ($newMatchdays >= $matchdaysToL1 && $oldLevel < ShortlistedPlayer::INTEL_REPORT) {
                // If one more matchday would also reach deep intel, skip straight to deep
                // to avoid two notifications firing across consecutive ticks in the same batch
                if (($newMatchdays + 1) >= $matchdaysToL2) {
                    $entry->update(['matchdays_tracked' => $newMatchdays, 'intel_level' => ShortlistedPlayer::INTEL_DEEP, 'is_tracking' => false]);
                } else {
                    $entry->update(['matchdays_tracked' => $newMatchdays, 'intel_level' => ShortlistedPlayer::INTEL_REPORT]);
                }
                $leveledUp->push($entry);
            } else {
                $entry->update(['matchdays_tracked' => $newMatchdays]);
            }
        }

        return $leveledUp;
    }

    /**
     * Calculate a player's willingness to transfer (0-100 score mapped to label).
     *
     * @return array{score: int, label: string}
     */
    public function calculateWillingness(GamePlayer $player, Game $game, ?float $importance = null): array
    {
        return $this->dispositionService->playerTransferWillingness($player, $game, $importance);
    }

    /**
     * Calculate whether rival clubs are also interested in a player.
     */
    public function calculateRivalInterest(GamePlayer $player, ?float $importance = null): bool
    {
        $overallAbility = ($player->current_technical_ability + $player->current_physical_ability) / 2;
        $importance ??= $this->calculatePlayerImportance($player);

        // Higher ability + lower importance = more likely rivals want them
        $chance = ($overallAbility / 99) * 0.4 + (1.0 - $importance) * 0.3;

        return rand(1, 100) <= (int) ($chance * 100);
    }
}
